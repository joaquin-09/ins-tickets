<?php

declare(strict_types=1);

try {
    require dirname(__DIR__) . '/bootstrap/app.php';
    InsTickets\Core\Database::connection()->query('SELECT COUNT(*) FROM categorias')->fetchColumn();
    exit(0);
} catch (Throwable $exception) {
    exit(1);
}
