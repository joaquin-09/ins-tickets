<?php

declare(strict_types=1);

use InsTickets\Core\Database;

require dirname(__DIR__) . '/bootstrap/app.php';

try {
    $pdo = Database::connection();
    $counts = ['usuarios' => 5, 'categorias' => 5, 'tickets' => 4, 'comentarios' => 2, 'historial_tickets' => 13, 'intentos_acceso' => 0];
    foreach ($counts as $table => $expected) {
        if ((int) $pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn() !== $expected) {
            throw new RuntimeException('Cantidad inesperada en ' . $table);
        }
    }
    $states = $pdo->query('SELECT estado,COUNT(*) AS cantidad FROM tickets GROUP BY estado')->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach (['abierto', 'en_progreso', 'resuelto', 'cerrado'] as $state) {
        if ((int) ($states[$state] ?? 0) !== 1) {
            throw new RuntimeException('Distribucion de estados inesperada.');
        }
    }
    $metrics = $pdo->query("SELECT AVG(TIMESTAMPDIFF(SECOND,created_at,first_response_at))/3600 AS respuesta,COUNT(first_response_at) AS n_respuesta,AVG(TIMESTAMPDIFF(SECOND,created_at,resolved_at))/3600 AS resolucion,COUNT(resolved_at) AS n_resolucion FROM tickets")->fetch();
    if (abs((float) $metrics['respuesta'] - 2) > 0.001 || abs((float) $metrics['resolucion'] - 6) > 0.001 || (int) $metrics['n_respuesta'] !== 2 || (int) $metrics['n_resolucion'] !== 2) {
        throw new RuntimeException('Los tiempos de la semilla no coinciden.');
    }
    foreach ($pdo->query('SELECT password_hash FROM usuarios')->fetchAll(PDO::FETCH_COLUMN) as $hash) {
        if (!password_verify('DemoLocal2026!', $hash) || (password_get_info($hash)['options']['cost'] ?? 0) !== 12) {
            throw new RuntimeException('Hash de demo inesperado.');
        }
    }
    echo "Semilla: OK\nEstados: 1 por cada estado\nPrimera respuesta: 2.00 h (n=2)\nResolucion: 6.00 h (n=2)\nHashes bcrypt costo 12: OK\n";
    echo "Consulta de solo lectura. Este chequeo corresponde exclusivamente a la semilla sin modificaciones.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "No coincide la semilla intacta o no fue posible consultarla. No se modificaron datos.\n");
    exit(1);
}
