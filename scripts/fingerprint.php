<?php

declare(strict_types=1);

use InsTickets\Core\Config;
use InsTickets\Core\Database;
use InsTickets\Verification\Verification;

require dirname(__DIR__) . '/bootstrap/app.php';
require __DIR__ . '/support/Verification.php';

try {
    if (PHP_SAPI !== 'cli' || Config::get('APP_ENV') !== 'local' || Config::get('DB_HOST') !== 'db') {
        throw new RuntimeException('Solo para respaldo de la instalación Docker local.');
    }
    $pdo = Database::connection();
    $pdo->beginTransaction();
    $tables = Verification::fingerprint($pdo);
    $pdo->commit();
    echo json_encode(['mysql' => $pdo->query('SELECT VERSION()')->fetchColumn(), 'tables' => $tables], JSON_THROW_ON_ERROR);
} catch (Throwable) {
    fwrite(STDERR, "No se pudo calcular la huella del respaldo.\n");
    exit(1);
}
