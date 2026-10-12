<?php

declare(strict_types=1);

use InsTickets\Core\Database;

try {
    require dirname(__DIR__) . '/bootstrap/app.php';
    $pdo = Database::connection();
    $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
    if (!str_starts_with($version, '8.4.')) {
        throw new RuntimeException('La base no utiliza MySQL 8.4.x.');
    }
    $account = (string) $pdo->query('SELECT CURRENT_USER()')->fetchColumn();
    if (!str_starts_with($account, 'ins_app@')) {
        throw new RuntimeException('La aplicacion no esta usando ins_app.');
    }
    $expected = ['categorias', 'comentarios', 'historial_tickets', 'intentos_acceso', 'tickets', 'usuarios'];
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    sort($tables);
    if ($tables !== $expected) {
        throw new RuntimeException('El esquema no coincide con las seis tablas esperadas.');
    }
    printf("PHP: %s\nMySQL: %s\nCuenta de aplicacion: ins_app\nTablas: 6 OK\n", PHP_VERSION, $version);
    foreach ($expected as $table) {
        // Lista interna fija, nunca entradas del cliente.
        printf("%s: %d\n", $table, $pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn());
    }
    echo "Conexion PDO y lectura del esquema: OK\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "Comprobacion fallida. Revisá servicios, esquema y configuración; no se modificaron datos.\n");
    error_log('[check] ' . get_class($exception));
    exit(1);
}
