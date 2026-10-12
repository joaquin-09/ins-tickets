<?php

declare(strict_types=1);

namespace InsTickets\Core;

final class Router
{
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH);
        $handler = $this->routes[$method][$path ?: '/'] ?? null;
        if ($handler === null) {
            View::render('errors/error', ['title' => 'Página no encontrada', 'message' => 'La página solicitada no está disponible.'], 404);
            return;
        }
        $handler();
    }
}
