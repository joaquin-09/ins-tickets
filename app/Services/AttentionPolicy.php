<?php

declare(strict_types=1);

namespace InsTickets\Services;

use InsTickets\Models\User;

final class AttentionPolicy
{
    public static function assign(User $actor, array $ticket): bool
    {
        return TicketPolicy::visible($actor, $ticket) && $actor->role === 'administrador' && in_array($ticket['estado'], ['abierto', 'en_progreso'], true);
    }

    public static function support(User $actor, array $ticket): bool
    {
        return TicketPolicy::visible($actor, $ticket) && $actor->id !== (int) $ticket['solicitante_id']
            && ($actor->role === 'administrador' || ($actor->role === 'agente' && (int) ($ticket['agente_id'] ?? 0) === $actor->id));
    }

    public static function start(User $actor, array $ticket): bool
    {
        return self::support($actor, $ticket) && $ticket['estado'] === 'abierto' && $ticket['agente_id'] !== null;
    }

    public static function resolve(User $actor, array $ticket): bool
    {
        return self::support($actor, $ticket) && $ticket['estado'] === 'en_progreso' && $ticket['agente_id'] !== null;
    }

    public static function close(User $actor, array $ticket): bool
    {
        return TicketPolicy::visible($actor, $ticket) && $ticket['estado'] === 'resuelto'
            && ($actor->role === 'administrador' || $actor->id === (int) $ticket['solicitante_id']);
    }

    public static function comment(User $actor, array $ticket): bool
    {
        return TicketPolicy::visible($actor, $ticket) && $ticket['estado'] !== 'cerrado';
    }

    public static function firstResponse(User $actor, array $ticket): bool
    {
        return self::support($actor, $ticket) && in_array($ticket['estado'], ['abierto', 'en_progreso'], true);
    }
}
