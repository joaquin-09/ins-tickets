<?php

declare(strict_types=1);

namespace InsTickets\Core;

final class Csrf
{
    public static function token(): string
    {
        if (!isset($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function valid(mixed $token): bool
    {
        return is_string($token) && isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
    }

    public static function verify(): void
    {
        if (!self::valid($_POST['_csrf'] ?? null)) {
            throw new HttpException(403, 'La verificación del formulario falló. Recargá la página e intentá nuevamente.');
        }
    }
}
