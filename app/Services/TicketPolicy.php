<?php

declare(strict_types=1);

namespace InsTickets\Services;

use InsTickets\Models\User;

final class TicketPolicy
{
    public static function visible(User $user, array $ticket): bool
    {
        return $user->active && ($user->role === 'administrador' || (int) $ticket['solicitante_id'] === $user->id || ($user->role === 'agente' && (int) ($ticket['agente_id'] ?? 0) === $user->id));
    }

    public static function text(User $user, array $ticket): bool
    {
        return self::visible($user, $ticket) && $ticket['estado'] === 'abierto' && ($user->role === 'administrador' || (int) $ticket['solicitante_id'] === $user->id);
    }

    public static function classification(User $user, array $ticket): bool
    {
        if (!self::visible($user, $ticket)) {
            return false;
        }
        return ($ticket['estado'] === 'abierto' && ($user->role === 'administrador' || (int) $ticket['solicitante_id'] === $user->id))
            || (in_array($ticket['estado'], ['abierto', 'en_progreso'], true) && ($user->role === 'administrador' || ($user->role === 'agente' && (int) ($ticket['agente_id'] ?? 0) === $user->id)));
    }
}
