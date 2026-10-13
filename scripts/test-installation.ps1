# Instala HEAD en carpeta, proyecto Docker y puerto separados; conserva la demo.
. (Join-Path $PSScriptRoot 'support/docker.ps1')
$root = Split-Path -Parent $PSScriptRoot
Push-Location $root
try {
    $state = Get-RepositoryState
    if ($state.dirty) { throw 'La instalación reproducible requiere HEAD limpio con el incremento 6 confirmado.' }
    $suffix = [Guid]::NewGuid().ToString('N').Substring(0,8)
    $project = 'ins-tickets-installation-' + $suffix
    $folder = Join-Path $root ('backups/installation-' + $suffix)
    [IO.Directory]::CreateDirectory($folder) | Out-Null
    $zip = Join-Path $folder 'source.zip'
    & git archive --format=zip --output=$zip HEAD
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo exportar HEAD.' }
    $code = Join-Path $folder 'codigo'
    Expand-Archive $zip $code
    Push-Location $code
    try {
        & (Join-Path $code 'scripts/setup-env.ps1') -Port 8082
        Invoke-DockerChecked @('compose','-p',$project,'config','--quiet')
        Invoke-DockerChecked @('compose','-p',$project,'build','app')
        Invoke-DockerChecked @('compose','-p',$project,'up','-d','--wait','db')
        Invoke-DockerChecked @('compose','-p',$project,'run','--rm','--no-deps','app','composer','install','--no-interaction','--prefer-dist')
        Invoke-DockerChecked @('compose','-p',$project,'run','--rm','app','php','scripts/check.php')
        Invoke-DockerChecked @('compose','-p',$project,'run','--rm','app','php','scripts/seed.php')
        Invoke-DockerChecked @('compose','-p',$project,'up','-d','--wait','app')
        Invoke-DockerChecked @('compose','-p',$project,'exec','-T','app','php','scripts/check-demo.php')
        Invoke-DockerChecked @('compose','-p',$project,'exec','-T','app','php','scripts/lint.php')
        $page = Invoke-WebRequest -UseBasicParsing -Uri 'http://localhost:8082/login'
        if ($page.StatusCode -ne 200 -or $page.Content -notmatch 'name="_csrf"') { throw 'No se pudo verificar login de instalación nueva.' }
        $result = [ordered]@{recorded_at_utc=[DateTime]::UtcNow.ToString('o'); source_commit=$state.commit; passed=$true;
            project=$project; directory=$code; port=8082; checks='Exportación limpia, configuración, build, Composer, esquema, semilla intacta, lint y GET login. Navegación manual todavía requerida.'}
        Write-Utf8Json (Join-Path $root 'storage/reports/installation.json') $result
        Write-Host ('Instalación separada iniciada: http://localhost:8082 | proyecto ' + $project)
        Write-Host ('Para detenerla tras la revisión: Set-Location "' + $code + '"; docker compose -p ' + $project + ' stop')
    } finally { Pop-Location }
} finally { Pop-Location }
