param([switch]$Manual, [ValidateRange(1024,65535)][int]$Port = 8080)
$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$target = Join-Path $root '.env'
if (Test-Path $target) { throw '.env ya existe. No se sobrescribió. Conservá su contenido.' }
function New-Secret {
    $bytes = New-Object byte[] 32
    $rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try { $rng.GetBytes($bytes) } finally { $rng.Dispose() }
    return [BitConverter]::ToString($bytes).Replace('-', '').ToLowerInvariant()
}
$text = [IO.File]::ReadAllText((Join-Path $root '.env.example'))
$text = $text.Replace('APP_KEY=', ('APP_KEY=' + (New-Secret)))
$text = $text.Replace('DB_PASSWORD=', ('DB_PASSWORD=' + (New-Secret)))
$text = $text.Replace('MYSQL_ROOT_PASSWORD=', ('MYSQL_ROOT_PASSWORD=' + (New-Secret)))
$text = $text.Replace('APP_PORT=8080', ('APP_PORT=' + $Port))
$text = $text.Replace('APP_URL=http://localhost:8080', ('APP_URL=http://localhost:' + $Port))
if ($Manual) { $text = $text.Replace('DB_HOST=db', 'DB_HOST=127.0.0.1') }
[IO.File]::WriteAllText($target, $text, (New-Object System.Text.UTF8Encoding($false)))
Write-Host '.env creado con secretos locales aleatorios. No lo agregués a Git.'
