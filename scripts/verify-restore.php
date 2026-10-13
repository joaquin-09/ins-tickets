<?php

declare(strict_types=1);

use InsTickets\Core\Database;
use InsTickets\Verification\Verification;

require dirname(__DIR__) . '/bootstrap/app.php';
require __DIR__ . '/support/Verification.php';

try {
    Verification::isolated('db-restore');
    $manifest = json_decode(file_get_contents($argv[1] ?? ''), true, 512, JSON_THROW_ON_ERROR);
    $pdo = Verification::maintenance();
    $actual = Verification::fingerprint($pdo);
    if ($actual !== $manifest['database']['tables']) { throw new RuntimeException('Los datos restaurados no coinciden.'); }
    // Comprueba acceso real con la cuenta de aplicación después de reponer permisos.
    $app = Database::connection();
    foreach (Verification::TABLES as $table) {
        if ((int) $app->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn() !== $actual[$table]['count']) {
            throw new RuntimeException('Lectura de aplicación no coincidente.');
        }
    }
    Verification::evidence('restore', ['passed' => true, 'backup_sha256' => $manifest['dump_sha256'],
        'source_commit' => $manifest['source_commit'], 'mysql' => $pdo->query('SELECT VERSION()')->fetchColumn(),
        'tables' => $actual, 'checks' => 'Huella de contenido de las seis tablas y lecturas con ins_app. No acredita navegación manual.']);
    echo "Restauración aislada: seis tablas y hashes coincidentes; acceso ins_app OK.\n";
} catch (Throwable) {
    Verification::evidence('restore', ['passed' => false, 'error' => 'No se pudo verificar restauración exacta.']);
    fwrite(STDERR, "Restauración no acreditada; compartir salida y conservar demo.\n");
    exit(1);
}
