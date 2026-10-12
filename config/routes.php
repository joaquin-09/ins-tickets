<?php

declare(strict_types=1);

use InsTickets\Controllers\HomeController;
use InsTickets\Core\Router;

$router = new Router();
$router->get('/', [new HomeController(), 'index']);

return $router;
