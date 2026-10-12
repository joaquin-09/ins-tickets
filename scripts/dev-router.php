<?php

declare(strict_types=1);

// Solo para php -S 127.0.0.1:8080 -t public scripts/dev-router.php.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$public = realpath(dirname(__DIR__) . '/public');
$file = realpath($public . $path);
if ($file !== false && str_starts_with($file, $public . DIRECTORY_SEPARATOR) && is_file($file)) {
    return false;
}
require $public . '/index.php';
