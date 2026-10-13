$ErrorActionPreference = 'Stop'
function Invoke-DockerText([string[]]$Arguments) {
    $result = & docker @Arguments
    if ($LASTEXITCODE -ne 0) { throw "Falló docker $($Arguments[0]); no se generó el manifiesto." }
    return ($result -join "`n")
}
$root = Split-Path -Parent $PSScriptRoot
Push-Location $root
try {
    $appId = (Invoke-DockerText @('compose','images','-q','app')).Trim()
    $dbId = (Invoke-DockerText @('compose','images','-q','db')).Trim()
    if (!$appId -or !$dbId) { throw 'Iniciá los dos servicios antes de registrar versiones.' }
    $appImage = (Invoke-DockerText @('image','inspect',$appId) | ConvertFrom-Json)[0]
    $dbImage = (Invoke-DockerText @('image','inspect',$dbId) | ConvertFrom-Json)[0]
    $manifest = [ordered]@{
        recorded_at_utc = [DateTime]::UtcNow.ToString('o')
        baseline = '1.3'
        increment = '05-reportes'
        php = Invoke-DockerText @('compose','exec','-T','app','php','-r','echo PHP_VERSION;')
        mysql = Invoke-DockerText @('compose','exec','-T','db','mysql','--version')
        composer = Invoke-DockerText @('compose','exec','-T','app','composer','--version')
        app_image_id = $appImage.Id
        mysql_image_id = $dbImage.Id
        mysql_repo_digests = $dbImage.RepoDigests
        dockerfile = [IO.File]::ReadAllText((Join-Path $root 'docker/php/Dockerfile'))
    }
    $directory = Join-Path $root 'storage/reports'
    [IO.Directory]::CreateDirectory($directory) | Out-Null
    $target = Join-Path $directory 'runtime.json'
    [IO.File]::WriteAllText($target, ($manifest | ConvertTo-Json -Depth 5), (New-Object System.Text.UTF8Encoding($false)))
    Write-Host 'Versiones e imágenes registradas en storage/reports/runtime.json (sin secretos).'
} finally { Pop-Location }
