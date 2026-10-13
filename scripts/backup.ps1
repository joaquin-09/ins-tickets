# Respaldo sin redirección de bytes en PowerShell 5.1. No elimina datos.
. (Join-Path $PSScriptRoot 'support/docker.ps1')
$root = Split-Path -Parent $PSScriptRoot
Push-Location $root
$stopped = $false
try {
    $state = Get-RepositoryState
    if ($state.dirty) { throw 'Crear primero el commit del código. El respaldo de código usa HEAD limpio.' }
    $stamp = [DateTime]::UtcNow.ToString('yyyyMMdd-HHmmss') + '-' + [Guid]::NewGuid().ToString('N').Substring(0,8)
    $directory = Join-Path $root ('backups/' + $stamp)
    [IO.Directory]::CreateDirectory($directory) | Out-Null
    $dump = Join-Path $directory 'database.sql'
    $code = Join-Path $directory 'code.zip'
    $running = @(Invoke-DockerChecked @('compose','ps','--services','--status','running'))
    if ($running -contains 'app') {
        Invoke-DockerChecked @('compose','stop','app') | Out-Null
        $stopped = $true
    }
    # Cuenta de aplicación para leer; secreto root solo dentro del contenedor db.
    $fingerprint = (Invoke-DockerChecked @('compose','run','--rm','--no-deps','app','php','scripts/fingerprint.php') -join "`n") | ConvertFrom-Json
    Invoke-DockerChecked @('compose','exec','-T','db','sh','-c','MYSQL_PWD=$MYSQL_ROOT_PASSWORD mysqldump -u root --single-transaction --skip-lock-tables --no-tablespaces --set-gtid-purged=OFF --column-statistics=0 --skip-add-drop-table --skip-comments --result-file=/tmp/ins-tickets-backup.sql ins_tickets') | Out-Null
    Invoke-DockerChecked @('compose','cp','db:/tmp/ins-tickets-backup.sql',$dump) | Out-Null
    if ((Get-Item $dump).Length -eq 0) { throw 'El dump está vacío.' }
    & git archive --format=zip --output=$code HEAD
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo respaldar el código.' }
    $manifest = [ordered]@{
        recorded_at_utc=[DateTime]::UtcNow.ToString('o')
        source_commit=$state.commit
        source_dirty=$false
        database=$fingerprint
        dump_sha256=(Get-FileHash $dump -Algorithm SHA256).Hash.ToLowerInvariant()
        code_sha256=(Get-FileHash $code -Algorithm SHA256).Hash.ToLowerInvariant()
        note='Dump privado con datos y hashes de contraseñas. No contiene .env; código de HEAD limpio. Credenciales/configuración se conservan aparte.'
    }
    Write-Utf8Json (Join-Path $directory 'manifest.json') $manifest
    Write-Host ('Respaldo creado: ' + $directory)
    Write-Host 'Conservar .env por separado de forma privada. Este respaldo todavía requiere ensayo de restauración.'
} finally {
    if ($stopped) { Invoke-DockerChecked @('compose','up','-d','app') | Out-Null }
    Pop-Location
}
