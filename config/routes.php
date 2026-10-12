<?php

declare(strict_types=1);

use InsTickets\Controllers\HomeController;
use InsTickets\Controllers\AuthController;
use InsTickets\Controllers\UserController;
use InsTickets\Controllers\CategoryController;
use InsTickets\Core\Router;

$router = new Router();
$router->get('/', [new HomeController(), 'index']);
$auth = new AuthController();
$router->get('/login', [$auth, 'form']);
$router->post('/login', [$auth, 'login']);
$router->post('/logout', [$auth, 'logout']);
$router->get('/cuenta/password', [$auth, 'passwordForm']);
$router->post('/cuenta/password', [$auth, 'password']);
$users = new UserController();
$router->get('/usuarios', [$users, 'index']);
$router->get('/usuarios/formulario', [$users, 'form']);
$router->post('/usuarios/salvar', [$users, 'save']);
$router->get('/usuarios/confirmar-eliminacion', [$users, 'deleteForm']);
$router->post('/usuarios/eliminar', [$users, 'delete']);
$categories = new CategoryController();
$router->get('/categorias', [$categories, 'index']);
$router->get('/categorias/formulario', [$categories, 'form']);
$router->post('/categorias/salvar', [$categories, 'save']);
$router->get('/categorias/confirmar-eliminacion', [$categories, 'deleteForm']);
$router->post('/categorias/eliminar', [$categories, 'delete']);

return $router;
