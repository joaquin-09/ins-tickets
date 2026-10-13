$ErrorActionPreference = 'Stop'
function Invoke-DockerChecked([string[]]$Arguments) {
    $result = & docker @Arguments
    if ($LASTEXITCODE -ne 0) { throw "Falló docker $($Arguments[0]). Detenerse y conservar los datos." }
    return $result
}
function Get-RepositoryState {
    $commit = (& git rev-parse HEAD).Trim()
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo identificar el commit.' }
    $changes = & git status --porcelain
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo consultar Git.' }
    return [ordered]@{commit=$commit; dirty=[bool]$changes}
}
function Write-Utf8Json([string]$Path, $Value) {
    [IO.File]::WriteAllText($Path, ($Value | ConvertTo-Json -Depth 30), (New-Object System.Text.UTF8Encoding($false)))
}
