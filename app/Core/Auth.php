<?php

declare(strict_types=1);

namespace InsTickets\Core;

use InsTickets\Models\User;
use InsTickets\Repositories\UserRepository;

final class Auth
{
    private static ?User $user = null;

    public static function refresh(): void
    {
        self::$user = null;
        if (!isset($_SESSION['user_id'])) {
            return;
        }
        $user = (new UserRepository(Database::connection()))->find((int) $_SESSION['user_id']);
        if ($user === null || !$user->active) {
            Session::forgetIdentity();
            Session::flash('La sesión ya no está disponible. Iniciá sesión nuevamente.');
            return;
        }
        self::$user = $user;
    }

    public static function user(): ?User
    {
        return self::$user;
    }

    public static function requireUser(): User
    {
        if (self::$user === null) {
            self::redirect('/login');
        }
        return self::$user;
    }

    public static function requireAdmin(): User
    {
        $user = self::requireUser();
        if ($user->role !== 'administrador') {
            throw new HttpException(403, 'No tenés permiso para acceder a esta función.');
        }
        return $user;
    }

    public static function redirect(string $path): never
    {
        header('Location: ' . $path, true, 303);
        exit;
    }
}
