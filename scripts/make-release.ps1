param([Parameter(Mandatory=$true)][string]$AcademicDocument)
. (Join-Path $PSScriptRoot 'support/docker.ps1')
$root = Split-Path -Parent $PSScriptRoot
Push-Location $root
try {
    $state = Get-RepositoryState
    if ($state.dirty) { throw 'El paquete requiere HEAD limpio y código confirmado.' }
    $academic = (Resolve-Path $AcademicDocument).Path
    if ([IO.Path]::GetExtension($academic).ToLowerInvariant() -notin @('.pdf','.docx')) { throw 'Documento académico esperado en PDF o DOCX.' }
    $reports = Join-Path $root 'storage/reports'
    $acceptance = Join-Path $reports 'acceptance.csv'
    $rows = @(Import-Csv $acceptance)
    $template = @(Import-Csv (Join-Path $root 'docs/ACEPTACION.csv'))
    if ($rows.Count -ne $template.Count) { throw 'La aceptación está incompleta.' }
    foreach ($expected in $template) {
        $actual = @($rows | Where-Object { $_.id -eq $expected.id })
        if ($actual.Count -ne 1 -or $actual[0].resultado -ne 'PASS' -or !$actual[0].fecha -or !$actual[0].revisor -or !$actual[0].evidencia -or $actual[0].commit -ne $state.commit -or ($actual[0].defecto -and $actual[0].severidad -eq 'critica')) {
            throw ('Falta aceptación/evidencia o hay defecto en ' + $expected.id)
        }
    }
    $runtime = Get-Content -Raw (Join-Path $reports 'runtime.json') | ConvertFrom-Json
    if ($runtime.source_commit -ne $state.commit -or $runtime.source_dirty -ne $false) { throw 'Registrar versiones sobre el commit limpio antes de repetir las pruebas.' }
    foreach ($name in @('coverage-summary','performance','http-security','restore')) {
        $data = Get-Content -Raw (Join-Path $reports ($name + '.json')) | ConvertFrom-Json
        if ($data.passed -ne $true) { throw ('La comprobación no está aprobada: ' + $name) }
        if ($name -eq 'restore' -and $data.source_commit -ne $state.commit) { throw 'El respaldo restaurado corresponde a otro commit.' }
        if ($data.runtime.source_commit -ne $state.commit -or $data.runtime.source_dirty -ne $false) { throw ('Repetir sobre el commit limpio: ' + $name) }
    }
    foreach ($entry in @(@('unit-06.xml',179),@('suite-06.xml',209))) {
        [xml]$junit = Get-Content -Raw (Join-Path $reports $entry[0])
        $top = $junit.SelectSingleNode('/testsuites/testsuite')
        if (!$top -or [int]$top.tests -ne $entry[1]) { throw ('Suite incompleta: ' + $entry[0]) }
        foreach ($node in $junit.SelectNodes('//*[@failures or @errors or @skipped]')) {
            if ([int]$node.failures -gt 0 -or [int]$node.errors -gt 0 -or [int]$node.skipped -gt 0) { throw ('Suite con fallos/omisiones: ' + $entry[0]) }
        }
    }
    $installation = Get-Content -Raw (Join-Path $reports 'installation.json') | ConvertFrom-Json
    if ($installation.passed -ne $true -or $installation.source_commit -ne $state.commit) { throw 'Falta instalación comprobada de este commit.' }
    $stamp = [DateTime]::UtcNow.ToString('yyyyMMdd-HHmmss') + '-' + [Guid]::NewGuid().ToString('N').Substring(0,8)
    $folder = Join-Path $root ('backups/entrega-' + $stamp)
    [IO.Directory]::CreateDirectory($folder) | Out-Null
    $code = Join-Path $folder 'codigo.zip'
    & git archive --format=zip --output=$code HEAD
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo exportar código.' }
    $evidence = Join-Path $folder 'evidencia'
    [IO.Directory]::CreateDirectory($evidence) | Out-Null
    foreach ($name in @('runtime.json','unit-06.xml','suite-06.xml','coverage.xml','coverage-summary.json','performance.json','http-security.json','restore.json','installation.json','acceptance.csv')) {
        Copy-Item (Join-Path $reports $name) $evidence
    }
    Copy-Item $academic (Join-Path $folder ('documento-academico' + [IO.Path]::GetExtension($academic)))
    $manifest = [ordered]@{created_at_utc=[DateTime]::UtcNow.ToString('o'); source_commit=$state.commit; baseline='1.3'; files=@()}
    foreach ($file in Get-ChildItem $folder -File -Recurse) {
        $manifest.files += [ordered]@{path=$file.FullName.Substring($folder.Length+1); sha256=(Get-FileHash $file.FullName -Algorithm SHA256).Hash.ToLowerInvariant()}
    }
    Write-Utf8Json (Join-Path $folder 'manifest.json') $manifest
    $zip = $folder + '.zip'
    Compress-Archive -Path (Join-Path $folder '*') -DestinationPath $zip
    Write-Host ('Paquete: ' + $zip)
    Write-Host 'SQL privado y .env quedan fuera. No se creó etiqueta, commit ni publicación.'
} finally { Pop-Location }
