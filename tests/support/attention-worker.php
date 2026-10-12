<?php

declare(strict_types=1);

use InsTickets\Core\Database;
use InsTickets\Core\HttpException;
use InsTickets\Services\AttentionService;

require dirname(__DIR__) . '/integration-bootstrap.php';

$service = new AttentionService(Database::connection());
echo "READY\n";
flush();
try {
    $service->comment((int)$argv[1],(int)$argv[2],(int)$argv[3],'Comentario concurrente de prueba');
    echo json_encode(['status'=>200],JSON_THROW_ON_ERROR);
} catch (HttpException $exception) {
    echo json_encode(['status'=>$exception->status],JSON_THROW_ON_ERROR);
}
