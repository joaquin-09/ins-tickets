<?php

declare(strict_types=1);

use InsTickets\Core\Database;
use InsTickets\Verification\HttpClient;
use InsTickets\Verification\Verification;

require dirname(__DIR__) . '/bootstrap/app.php';
require __DIR__ . '/support/Verification.php';
require __DIR__ . '/support/HttpClient.php';

try {
    Verification::isolated();
    $pdo = Database::connection();
    if ((int) $pdo->query('SELECT COUNT(*) FROM tickets')->fetchColumn() !== 500) {
        throw new RuntimeException('La medición requiere exactamente 500 tickets sintéticos.');
    }
    $id = (int) $pdo->query('SELECT MIN(id) FROM tickets')->fetchColumn();
    $client = new HttpClient();
    $login = $client->login('admin@validation.test');
    $flows = ['lista' => '/tickets', 'detalle' => '/tickets/detalle?id=' . $id, 'reportes' => '/reportes'];
    $results = [];
    $passed = true;
    foreach ($flows as $key => $path) {
        if ($client->request($path)['status'] !== 200) { throw new RuntimeException('Falló calentamiento.'); }
        $samples = [];
        for ($i = 0; $i < 20; $i++) {
            $response = $client->request($path);
            if ($response['status'] !== 200) { throw new RuntimeException('Respuesta no válida durante medición.'); }
            $samples[] = $response['seconds'];
        }
        $ordered = $samples;
        sort($ordered, SORT_NUMERIC);
        $p95 = $ordered[(int) ceil(0.95 * count($ordered)) - 1];
        $results[$key] = ['samples_seconds' => $samples, 'n' => 20, 'p95_seconds' => $p95, 'under_3_seconds' => $p95 < 3];
        $passed = $passed && $p95 < 3;
        echo $key . ': p95=' . number_format($p95, 4) . " s (n=20)\n";
    }
    Verification::evidence('performance', ['mysql' => $pdo->query('SELECT VERSION()')->fetchColumn(),
        'tickets' => 500, 'measurement' => 'HTTP secuencial desde contenedor validation a Apache web-validation en Docker local; no incluye render de navegador',
        'excluded' => ['arranque', 'instalación', 'calentamiento', 'login'], 'login_seconds_separate' => $login['seconds'],
        'p95_method' => 'nearest rank: muestra 19 ordenada de 20', 'flows' => $results, 'passed' => $passed]);
    exit($passed ? 0 : 1);
} catch (Throwable) {
    Verification::evidence('performance', ['passed' => false, 'error' => 'No se completó una medición válida. Revisar servicios/dataset.']);
    fwrite(STDERR, "Medición fallida; no se acredita la meta.\n");
    exit(1);
}
