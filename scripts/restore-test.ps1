param([Parameter(Mandatory=$true)][string]$BackupDirectory)
. (Join-Path $PSScriptRoot 'support/docker.ps1')
$root = Split-Path -Parent $PSScriptRoot
Push-Location $root
try {
    $directory = (Resolve-Path $BackupDirectory).Path
    $prefix = [IO.Path]::GetFullPath((Join-Path $root 'backups')) + [IO.Path]::DirectorySeparatorChar
    if (!$directory.StartsWith($prefix,[StringComparison]::OrdinalIgnoreCase)) { throw 'Seleccionar un respaldo dentro de backups del repositorio.' }
    $dump = Join-Path $directory 'database.sql'
    $manifestPath = Join-Path $directory 'manifest.json'
    $manifest = Get-Content -Raw $manifestPath | ConvertFrom-Json
    if ((Get-FileHash $dump -Algorithm SHA256).Hash.ToLowerInvariant() -ne $manifest.dump_sha256) { throw 'El dump no coincide con su manifiesto.' }
    $composeArgs = @('compose','-f','compose.yaml','-f','compose.validation.yaml','--profile','validation')
    Invoke-DockerChecked ($composeArgs + @('up','-d','--wait','db-restore')) | Out-Null
    $count = (Invoke-DockerChecked ($composeArgs + @('exec','-T','db-restore','sh','-c','MYSQL_PWD=$MYSQL_ROOT_PASSWORD mysql -u root ins_tickets -Nse ''SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()''')) -join '').Trim()
    if ($count -ne '0') { throw 'db-restore ya contiene tablas. No se sobrescribió nada; conservar resultado y compartir la salida.' }
    Invoke-DockerChecked ($composeArgs + @('cp',$dump,'db-restore:/tmp/ins-tickets-restore.sql')) | Out-Null
    Invoke-DockerChecked ($composeArgs + @('exec','-T','db-restore','sh','-c','MYSQL_PWD=$MYSQL_ROOT_PASSWORD mysql -u root ins_tickets < /tmp/ins-tickets-restore.sql')) | Out-Null
    Invoke-DockerChecked ($composeArgs + @('cp','database/permissions.sql','db-restore:/tmp/ins-tickets-permissions.sql')) | Out-Null
    Invoke-DockerChecked ($composeArgs + @('exec','-T','db-restore','sh','-c','MYSQL_PWD=$MYSQL_ROOT_PASSWORD mysql -u root ins_tickets < /tmp/ins-tickets-permissions.sql')) | Out-Null
    $relative = $directory.Substring($root.Length).TrimStart([char[]]'\/').Replace('\','/')
    Invoke-DockerChecked ($composeArgs + @('run','--rm','restore-check','php','scripts/verify-restore.php',('/var/www/html/' + $relative + '/manifest.json')))
    Invoke-DockerChecked ($composeArgs + @('up','-d','--wait','web-restored')) | Out-Null
    Write-Host 'Consultar http://localhost:8083 para comprobar login y reportes de la copia restaurada.'
    Write-Host 'Solo db-restore recibió el dump. La base de exposición se conservó.'
} finally { Pop-Location }
