param(
    [Parameter(Position = 0)]
    [ValidateSet('setup', 'up', 'down', 'test', 'quality', 'e2e', 'seed', 'demo', 'load', 'logs', 'build', 'production-build', 'reset')]
    [string] $Task = 'up'
)

$ErrorActionPreference = 'Stop'
$Root = Split-Path -Parent $PSScriptRoot
Set-Location $Root

function Invoke-Compose {
    param([Parameter(ValueFromRemainingArguments = $true)][string[]] $Arguments)

    & docker compose @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "docker compose failed with exit code $LASTEXITCODE"
    }
}
function Initialize-EnvironmentFiles {
    $Files = @(
        @{ Example = '.env.example'; Target = '.env' },
        @{ Example = 'apps/backend/.env.example'; Target = 'apps/backend/.env' },
        @{ Example = 'apps/frontend/.env.example'; Target = 'apps/frontend/.env' }
    )

    foreach ($File in $Files) {
        if (-not (Test-Path -LiteralPath $File.Target)) {
            Copy-Item -LiteralPath $File.Example -Destination $File.Target
        }
    }
}
function Invoke-ComposeContractTests {
    $VariableNames = @(
        'APP_PORT',
        'APP_URL',
        'FRONTEND_URL',
        'COMPOSE_PUBLIC_ORIGIN',
        'SANCTUM_STATEFUL_DOMAINS',
        'CORS_ALLOWED_ORIGINS'
    )
    $PreviousValues = @{}

    foreach ($Name in $VariableNames) {
        $PreviousValues[$Name] = [Environment]::GetEnvironmentVariable($Name, 'Process')
    }

    try {
        $env:APP_PORT = '18080'
        Remove-Item Env:\COMPOSE_PUBLIC_ORIGIN -ErrorAction SilentlyContinue
        $DevelopmentConfig = & docker compose --profile tools config --format json
        if ($LASTEXITCODE -ne 0) {
            throw 'Unable to render the development Compose configuration.'
        }
        $DevelopmentConfig | & docker compose run --rm --no-deps -T frontend node /workspace/tests/infra/assert-compose-config.mjs development 18080
        if ($LASTEXITCODE -ne 0) {
            throw 'The development Compose origin contract failed.'
        }

        $ProductionOrigin = 'https://proof.example.test'
        $env:APP_URL = $ProductionOrigin
        $env:FRONTEND_URL = $ProductionOrigin
        $env:SANCTUM_STATEFUL_DOMAINS = 'proof.example.test'
        $env:CORS_ALLOWED_ORIGINS = $ProductionOrigin
        $ProductionConfig = & docker compose -f compose.yaml -f compose.production.yaml config --format json
        if ($LASTEXITCODE -ne 0) {
            throw 'Unable to render the production Compose configuration.'
        }
        $ProductionConfig | & docker compose run --rm --no-deps -T frontend node /workspace/tests/infra/assert-compose-config.mjs production $ProductionOrigin
        if ($LASTEXITCODE -ne 0) {
            throw 'The production Compose origin contract failed.'
        }
    }
    finally {
        foreach ($Name in $VariableNames) {
            $PreviousValue = $PreviousValues[$Name]
            if ($null -eq $PreviousValue) {
                Remove-Item "Env:$Name" -ErrorAction SilentlyContinue
            }
            else {
                [Environment]::SetEnvironmentVariable($Name, $PreviousValue, 'Process')
            }
        }
    }
}

switch ($Task) {
    'setup' {
        Initialize-EnvironmentFiles
        Invoke-Compose build backend frontend
        Invoke-Compose run --rm backend composer install --no-interaction --prefer-dist
        Invoke-Compose run --rm --user root --workdir /workspace/packages/deadline-engine backend chown -R app:app vendor
        Invoke-Compose run --rm --workdir /workspace/packages/deadline-engine backend composer install --no-interaction --prefer-dist
        Invoke-Compose run --rm frontend npm ci
        Invoke-Compose run --rm backend php artisan key:generate --force
        Invoke-Compose run --rm backend php artisan migrate --force
        Invoke-Compose run --rm backend php artisan keepproof:storage:prepare --create
    }
    'up' {
        Initialize-EnvironmentFiles
        Invoke-Compose up --detach --wait
    }
    'down' { Invoke-Compose down --remove-orphans }
    'test' {
        Invoke-ComposeContractTests
        Invoke-Compose run --rm backend composer test
        Invoke-Compose run --rm --workdir /workspace/packages/deadline-engine backend composer test
        Invoke-Compose run --rm frontend npm run test:unit
    }
    'quality' {
        Invoke-ComposeContractTests
        Invoke-Compose run --rm backend composer quality
        Invoke-Compose run --rm --workdir /workspace/packages/deadline-engine backend composer check
        Invoke-Compose run --rm frontend npm run quality
    }
    'e2e' {
        Initialize-EnvironmentFiles
        Invoke-ComposeContractTests
        $PreviousComposePublicOrigin = $env:COMPOSE_PUBLIC_ORIGIN
        $PreviousOcrDriver = $env:OCR_DRIVER

        try {
            $env:COMPOSE_PUBLIC_ORIGIN = 'http://nginx:8080'
            $env:OCR_DRIVER = 'fixture'
            Invoke-Compose up --detach --wait
            Invoke-Compose --profile tools run --rm e2e
        }
        finally {
            $env:COMPOSE_PUBLIC_ORIGIN = $PreviousComposePublicOrigin
            $env:OCR_DRIVER = $PreviousOcrDriver
            # Recreate affected services with their normal public origin even when E2E fails.
            Invoke-Compose up --detach --wait
        }
    }
    'seed' {
        Invoke-Compose run --rm backend php artisan migrate --force
        Invoke-Compose run --rm backend php artisan keepproof:storage:prepare --create
        Invoke-Compose run --rm backend php artisan db:seed --force
    }
    'demo' {
        Initialize-EnvironmentFiles
        Invoke-Compose up --detach --wait
        Invoke-Compose run --rm backend php artisan migrate --force
        Invoke-Compose run --rm backend php artisan keepproof:storage:prepare --create
        Invoke-Compose run --rm backend php artisan db:seed --force
        $PublishedAddress = (& docker compose port nginx 8080).Trim()
        if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($PublishedAddress)) {
            throw 'Unable to determine the published KeepProof application port.'
        }
        $AppPort = ($PublishedAddress -split ':')[-1]
        Write-Output "KeepProof: http://localhost:$AppPort"
        Write-Output 'Mailpit: http://localhost:8025'
    }
    'load' {
        Initialize-EnvironmentFiles
        Invoke-Compose up --detach --wait
        Invoke-Compose run --rm backend php artisan migrate --force
        Invoke-Compose run --rm backend php artisan keepproof:storage:prepare --create
        Invoke-Compose run --rm backend php artisan db:seed --force
        Invoke-Compose exec -T frontend node /workspace/tests/load/run.mjs
    }
    'logs' { Invoke-Compose logs --follow --tail=200 }
    'build' { Invoke-Compose build }
    'production-build' { Invoke-Compose -f compose.yaml -f compose.production.yaml build }
    'reset' {
        Invoke-Compose down --volumes --remove-orphans
        Write-Output 'Removed KeepProof containers and named development volumes.'
    }
}
