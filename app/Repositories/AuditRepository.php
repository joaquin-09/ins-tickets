<?php

declare(strict_types=1);

namespace InsTickets\Repositories;

use PDO;

final class AuditRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function append(int $ticketId, int $actorId, string $type, ?array $before, array $after, ?string $createdAt = null): void
    {
        $statement = $this->pdo->prepare('INSERT INTO historial_tickets (ticket_id,actor_id,tipo,anterior,nuevo,created_at) VALUES (?,?,?,?,?,COALESCE(?,UTC_TIMESTAMP(6)))');
        $statement->execute([$ticketId, $actorId, $type, $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), json_encode($after, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $createdAt]);
    }
}
