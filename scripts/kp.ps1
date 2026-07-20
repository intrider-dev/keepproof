param(
    [Parameter(Position = 0)]
    [ValidateSet('setup', 'up', 'down', 'test', 'quality', 'e2e', 'seed', 'demo', 'logs', 'build', 'production-build', 'reset')]
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
        Invoke-Compose run --rm frontend npm ci
        Invoke-Compose run --rm backend php artisan key:generate --force
        Invoke-Compose run --rm backend php artisan migrate --force
        Invoke-Compose run --rm backend php artisan storage:link --force
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
        Invoke-Compose run --rm --workdir /workspace/packages/deadline-engine backend composer quality
        Invoke-Compose run --rm frontend npm run quality
    }
    'e2e' {
        Initialize-EnvironmentFiles
        Invoke-Compose up --detach --wait
        Invoke-Compose --profile tools run --rm e2e
    }
    'seed' {
        Invoke-Compose run --rm backend php artisan migrate --force
        Invoke-Compose run --rm backend php artisan db:seed --force
    }
    'demo' {
        Initialize-EnvironmentFiles
        Invoke-Compose up --detach --wait
        Invoke-Compose run --rm backend php artisan migrate --force
        Invoke-Compose run --rm backend php artisan db:seed --force
        Write-Output 'KeepProof: http://localhost:8080'
        Write-Output 'Mailpit: http://localhost:8025'
    }
    'logs' { Invoke-Compose logs --follow --tail=200 }
    'build' { Invoke-Compose build }
    'production-build' { Invoke-Compose -f compose.yaml -f compose.production.yaml build }
    'reset' {
        Invoke-Compose down --volumes --remove-orphans
        Write-Output 'Removed KeepProof containers and named development volumes.'
    }
}
