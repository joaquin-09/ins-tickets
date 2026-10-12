<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
header('Cache-Control: no-store');

try {
    require dirname(__DIR__) . '/bootstrap/app.php';
    $router = require dirname(__DIR__) . '/config/routes.php';
    $router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
} catch (Throwable $exception) {
    $reference = bin2hex(random_bytes(6));
    // Datos del incidente solo en almacenamiento privado, nunca en la respuesta.
    error_log(sprintf('[%s] %s at %s:%d', $reference, get_class($exception), $exception->getFile(), $exception->getLine()));
    http_response_code(500);
    echo '<!doctype html><html lang="es"><meta charset="utf-8"><title>Error</title><h1>No se pudo completar la solicitud</h1><p>Intentá nuevamente. Referencia: ' . $reference . '</p></html>';
}
