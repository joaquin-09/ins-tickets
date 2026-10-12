<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/app.php';

if (getenv('APP_ENV') !== 'test' || getenv('DB_HOST') !== 'db-test' || getenv('DB_NAME') !== 'ins_tickets') {
    throw new RuntimeException('Pruebas de integración rechazadas: requieren el servidor aislado db-test y APP_ENV=test.');
}
