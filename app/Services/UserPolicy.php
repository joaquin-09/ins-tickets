<?php

declare(strict_types=1);

namespace InsTickets\Services;

use InsTickets\Core\HttpException;
use InsTickets\Core\ValidationException;
use InsTickets\Models\User;

final class UserPolicy
{
    public static function admin(?User $actor): void
    {
        if ($actor === null || !$actor->active || $actor->role !== 'administrador') {
            throw new HttpException(403, 'No tenés permiso para administrar estos registros.');
        }
    }

    public static function change(User $actor, User $target, string $role, bool $active, int $activeAdmins, int $pendingTickets, bool $deleting = false): void
    {
        self::admin($actor);
        if ($actor->id === $target->id && ($deleting || !$active || $role !== 'administrador')) {
            throw new ValidationException(['general' => 'No podés eliminarte, desactivarte ni quitarte el rol de administrador.']);
        }
        if ($target->role === 'administrador' && $target->active && $activeAdmins <= 1 && ($deleting || !$active || $role !== 'administrador')) {
            throw new ValidationException(['general' => 'Debe conservarse al menos un administrador activo.']);
        }
        if ($target->role === 'agente' && ($deleting || !$active || $role !== 'agente') && $pendingTickets > 0) {
            throw new ValidationException(['general' => 'Reasigná primero sus tickets abiertos o en progreso.']);
        }
    }
}
