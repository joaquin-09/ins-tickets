<?php

declare(strict_types=1);

$path = $argv[1] ?? dirname(__DIR__) . '/storage/reports/coverage.xml';
if (!is_file($path)) {
    fwrite(STDERR, "No existe reporte de cobertura.\n");
    exit(1);
}
$document = new DOMDocument();
$previous = libxml_use_internal_errors(true);
$loaded = $document->load($path, LIBXML_NONET);
libxml_clear_errors();
libxml_use_internal_errors($previous);
if (!$loaded) { fwrite(STDERR, "Reporte inválido.\n"); exit(1); }
$xpath = new DOMXPath($document);
$rows = [];
$statements = 0;
$covered = 0;
foreach ($xpath->query('//file') as $file) {
    $metric = $xpath->query('./metrics', $file)->item(0);
    if ($metric === null) { continue; }
    $total = (int) $metric->getAttribute('statements');
    $hit = (int) $metric->getAttribute('coveredstatements');
    $name = basename($file->getAttribute('name'));
    $rows[$name] = ['statements' => $total, 'covered' => $hit, 'percent' => $total === 0 ? null : 100 * $hit / $total];
    $statements += $total;
    $covered += $hit;
}
$required = ['AttentionService.php', 'AuthService.php', 'CategoryService.php', 'TicketService.php', 'UserService.php'];
$missing = array_values(array_diff($required, array_keys($rows)));
$criticalTotal = 0;
$criticalCovered = 0;
foreach ($required as $name) {
    $criticalTotal += $rows[$name]['statements'] ?? 0;
    $criticalCovered += $rows[$name]['covered'] ?? 0;
}
$criticalPercent = $criticalTotal === 0 ? 0 : 100 * $criticalCovered / $criticalTotal;
$runtimePath = dirname(__DIR__) . '/storage/reports/runtime.json';
$runtime = is_file($runtimePath) ? json_decode(file_get_contents($runtimePath), true, 512, JSON_THROW_ON_ERROR) : null;
$percent = $statements === 0 ? 0 : 100 * $covered / $statements;
$passed = $missing === [] && $statements > 0 && $percent >= 80 && $criticalPercent >= 80;
$report = ['recorded_at_utc' => gmdate('c'), 'scope' => 'Todas las clases app/Services; cobertura de líneas ejecutables de unitarias más integración. No cobertura de ramas, procesos auxiliares ni HTTP externo.',
    'statements' => $statements, 'covered' => $covered, 'percent' => $percent, 'target_percent' => 80,
    'runtime' => $runtime, 'critical_services' => $required, 'critical_percent' => $criticalPercent, 'required_missing' => $missing, 'files' => $rows, 'passed' => $passed];
$target = dirname(__DIR__) . '/storage/reports/coverage-summary.json';
if (file_put_contents($target, json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)) === false) { exit(1); }
echo 'Cobertura de líneas app/Services: ' . number_format($percent, 2) . "%\n";
echo 'Servicios transaccionales críticos: ' . number_format($criticalPercent, 2) . "%\n";
foreach ($rows as $name => $row) { echo $name . ': ' . ($row['percent'] === null ? 'Sin líneas' : number_format($row['percent'], 2) . '%') . "\n"; }
echo $passed ? "Meta agregada >=80% cumplida en el ámbito declarado.\n" : "Meta pendiente; revisar líneas sin cubrir y agregar pruebas pertinentes.\n";
exit($passed ? 0 : 1);
