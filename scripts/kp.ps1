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
        Invoke-Compose run --rm backend composer test
        Invoke-Compose run --rm --workdir /workspace/packages/deadline-engine backend composer test
        Invoke-Compose run --rm frontend npm run test:unit
    }
    'quality' {
        Invoke-Compose run --rm backend composer quality
        Invoke-Compose run --rm --workdir /workspace/packages/deadline-engine backend composer check
        Invoke-Compose run --rm frontend npm run quality
    }
    'e2e' {
        Initialize-EnvironmentFiles
        $PreviousAppUrl = $env:APP_URL
        $PreviousFrontendUrl = $env:FRONTEND_URL
        $PreviousOcrDriver = $env:OCR_DRIVER

        try {
            $env:APP_URL = 'http://nginx:8080'
            $env:FRONTEND_URL = 'http://nginx:8080'
            $env:OCR_DRIVER = 'fixture'
            Invoke-Compose up --detach --wait
            Invoke-Compose --profile tools run --rm e2e
        }
        finally {
            $env:APP_URL = $PreviousAppUrl
            $env:FRONTEND_URL = $PreviousFrontendUrl
            $env:OCR_DRIVER = $PreviousOcrDriver
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
        $AppPort = if ([string]::IsNullOrWhiteSpace($env:APP_PORT)) { '8080' } else { $env:APP_PORT }
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
