# Ejecutar después de aplicar el incremento; detenerse ante el primer fallo.
. (Join-Path $PSScriptRoot 'support/docker.ps1')
$root = Split-Path -Parent $PSScriptRoot
Push-Location $root
try {
    & (Join-Path $PSScriptRoot 'record-runtime.ps1')
    Invoke-DockerChecked @('compose','exec','-T','app','php','scripts/lint.php')
    Invoke-DockerChecked @('compose','exec','-T','app','php','vendor/bin/phpunit','--log-junit','storage/reports/unit-06.xml')
    $test = @('compose','-f','compose.yaml','-f','compose.test.yaml','--profile','test')
    Invoke-DockerChecked ($test + @('config','--quiet'))
    Invoke-DockerChecked ($test + @('build','tests'))
    Invoke-DockerChecked ($test + @('run','--rm','tests','php','-d','pcov.enabled=1','-d','pcov.directory=/var/www/html/app/Services','vendor/bin/phpunit','-c','phpunit.coverage.xml','--log-junit','storage/reports/suite-06.xml','--coverage-clover','storage/reports/coverage.xml','--coverage-html','storage/reports/coverage-html'))
    Invoke-DockerChecked @('compose','exec','-T','app','php','scripts/check-coverage.php')
    $qa = @('compose','-f','compose.yaml','-f','compose.validation.yaml','--profile','validation')
    Invoke-DockerChecked ($qa + @('config','--quiet'))
    Invoke-DockerChecked ($qa + @('run','--rm','validation','php','scripts/seed-validation.php'))
    Invoke-DockerChecked ($qa + @('run','--rm','validation','php','scripts/measure-performance.php'))
    Invoke-DockerChecked ($qa + @('run','--rm','validation','php','scripts/validate-http.php'))
    Write-Host 'Comprobaciones automáticas aprobadas en este entorno. Faltan aceptación manual, commit, respaldo/restauración y paquete.'
} finally {
    # Parar servidores temporales conserva la demo; no borra volúmenes mysql_data.
    & docker compose -f compose.yaml -f compose.test.yaml --profile test stop db-test
    & docker compose -f compose.yaml -f compose.validation.yaml --profile validation stop web-validation db-validation
    Pop-Location
}
