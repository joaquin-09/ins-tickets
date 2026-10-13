<?php

declare(strict_types=1);

namespace InsTickets\Tests\Unit;

use InsTickets\Core\Csrf;
use InsTickets\Core\HttpException;
use InsTickets\Core\Router;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RoutingSecurityTest extends TestCase
{
    public static function writes(): array
    {
        return array_map(static fn (string $path): array => [$path], ['/login', '/logout', '/cuenta/password',
            '/usuarios/salvar', '/usuarios/eliminar', '/categorias/salvar', '/categorias/eliminar',
            '/tickets/crear', '/tickets/editar', '/tickets/asignar', '/tickets/transicion', '/tickets/comentar']);
    }

    #[DataProvider('writes')]
    public function testEveryWriteRouteRejectsMissingCsrfBeforeCallingServices(string $path): void
    {
        $_SESSION = [];
        $_POST = [];
        $router = require dirname(__DIR__, 2) . '/config/routes.php';
        try {
            $router->dispatch('POST', $path);
            self::fail('Se esperaba rechazo CSRF.');
        } catch (HttpException $exception) {
            self::assertSame(403, $exception->status);
        }
    }

    public function testWrongCsrfDoesNotInvokeHandlerAndValidTokenDoes(): void
    {
        $_SESSION = [];
        $called = false;
        $router = new Router();
        $router->post('/prueba', static function () use (&$called): void { $called = true; });
        $_POST = ['_csrf' => 'invalid'];
        try { $router->dispatch('POST', '/prueba'); self::fail('Debía rechazar.'); }
        catch (HttpException $exception) { self::assertSame(403, $exception->status); }
        self::assertFalse($called);
        $_POST = ['_csrf' => Csrf::token()];
        $router->dispatch('POST', '/prueba?ignored=1');
        self::assertTrue($called);
    }
}
