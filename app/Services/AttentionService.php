<?php

declare(strict_types=1);

namespace InsTickets\Services;

use InsTickets\Core\HttpException;
use InsTickets\Core\ValidationException;
use InsTickets\Models\User;
use InsTickets\Repositories\AuditRepository;
use PDO;

final class AttentionService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    private function operate(int $actorId, int $ticketId, int $version, callable $operation): mixed
    {
        $this->pdo->beginTransaction();
        try {
            // Mismo orden de bloqueo que administración/edición; incluye candidato y solicitante.
            $users = [];
            foreach ($this->pdo->query('SELECT * FROM usuarios ORDER BY id FOR UPDATE')->fetchAll() as $row) {
                $users[(int) $row['id']] = User::fromRow($row);
            }
            $actor = $users[$actorId] ?? null;
            if ($actor === null || !$actor->active) {
                throw new HttpException(403, 'La cuenta ya no autoriza esta operación.');
            }
            $query = $this->pdo->prepare('SELECT * FROM tickets WHERE id=? FOR UPDATE');
            $query->execute([$ticketId]);
            $ticket = $query->fetch();
            if (!$ticket || !TicketPolicy::visible($actor, $ticket)) {
                throw new HttpException(404, 'No se encontró el ticket.');
            }
            if ((int) $ticket['version'] !== $version) {
                throw new HttpException(409, 'El ticket cambió desde que abriste la página. Recargá y revisá la acción antes de reenviar.');
            }
            $now = (string) $this->pdo->query('SELECT UTC_TIMESTAMP(6)')->fetchColumn();
            $result = $operation($actor, $ticket, $users, $now);
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function activeAgent(?int $agentId, array $users, array $ticket): void
    {
        $agent = $agentId === null ? null : ($users[$agentId] ?? null);
        if ($agent === null || !$agent->active || $agent->role !== 'agente' || $agent->id === (int) $ticket['solicitante_id']) {
            throw new ValidationException(['agente_id' => 'Se requiere un agente activo distinto del solicitante.']);
        }
    }

    private function changed(\PDOStatement $query): void
    {
        if ($query->rowCount() !== 1) {
            throw new HttpException(409, 'No se confirmó el cambio de versión. Recargá el ticket.');
        }
    }

    public function assign(int $actorId, int $id, int $version, ?int $agentId): void
    {
        $this->operate($actorId, $id, $version, function (User $actor, array $ticket, array $users, string $now) use ($id, $version, $agentId): void {
            if ($actor->role !== 'administrador') {
                throw new HttpException(403, 'Solo administración puede asignar o reasignar.');
            }
            if (!AttentionPolicy::assign($actor, $ticket)) {
                throw new ValidationException(['general' => 'La asignación es inmutable después de resolver.']);
            }
            if ($agentId === null && $ticket['estado'] === 'en_progreso') {
                throw new ValidationException(['agente_id' => 'Un ticket en progreso debe conservar un agente válido.']);
            }
            if ($agentId !== null) {
                $this->activeAgent($agentId, $users, $ticket);
            }
            $previous = $ticket['agente_id'] === null ? null : (int) $ticket['agente_id'];
            if ($previous === $agentId) {
                throw new ValidationException(['agente_id' => 'El agente seleccionado ya corresponde a la asignación actual.']);
            }
            $query = $this->pdo->prepare('UPDATE tickets SET agente_id=?,version=version+1 WHERE id=? AND version=?');
            $query->execute([$agentId, $id, $version]);
            $this->changed($query);
            (new AuditRepository($this->pdo))->append($id, $actor->id, 'asignacion', ['agente_id' => $previous], ['agente_id' => $agentId], $now);
        });
    }

    public function transition(int $actorId, int $id, int $version, string $target, string $summary = ''): void
    {
        $this->operate($actorId, $id, $version, function (User $actor, array $ticket, array $users, string $now) use ($id, $version, $target, $summary): void {
            if (!in_array($target, ['en_progreso', 'resuelto', 'cerrado'], true)) {
                throw new ValidationException(['estado' => 'No se permiten reaperturas, reversión ni estados desconocidos.']);
            }
            if ($target === 'cerrado') {
                if ($actor->role !== 'administrador' && $actor->id !== (int) $ticket['solicitante_id']) {
                    throw new HttpException(403, 'El cierre corresponde al solicitante o administración.');
                }
                if (!AttentionPolicy::close($actor, $ticket)) {
                    throw new ValidationException(['estado' => 'Solo puede cerrarse un ticket resuelto; no se repiten transiciones.']);
                }
                $query = $this->pdo->prepare("UPDATE tickets SET estado='cerrado',closed_at=?,closed_by=?,version=version+1 WHERE id=? AND version=?");
                $query->execute([$now, $actor->id, $id, $version]);
                $after = ['estado' => 'cerrado', 'closed_at' => $now, 'closed_by' => $actor->id];
            } else {
                if (!AttentionPolicy::support($actor, $ticket)) {
                    throw new HttpException(403, 'La atención corresponde al agente asignado o administrador, siempre distinto del solicitante.');
                }
                $this->activeAgent($ticket['agente_id'] === null ? null : (int) $ticket['agente_id'], $users, $ticket);
                if ($target === 'en_progreso') {
                    if (!AttentionPolicy::start($actor, $ticket)) {
                        throw new ValidationException(['estado' => 'Solo un ticket abierto puede iniciar atención.']);
                    }
                    $query = $this->pdo->prepare("UPDATE tickets SET estado='en_progreso',version=version+1 WHERE id=? AND version=?");
                    $query->execute([$id, $version]);
                    $after = ['estado' => 'en_progreso'];
                } else {
                    if (!AttentionPolicy::resolve($actor, $ticket)) {
                        throw new ValidationException(['estado' => 'Solo un ticket en progreso puede resolverse.']);
                    }
                    $summary = AttentionValidation::text($summary, 'resolution_summary', 10, 2000);
                    $query = $this->pdo->prepare("UPDATE tickets SET estado='resuelto',resolved_at=?,resolved_by=?,resolution_summary=?,version=version+1 WHERE id=? AND version=?");
                    $query->execute([$now, $actor->id, $summary, $id, $version]);
                    $after = ['estado' => 'resuelto', 'resolved_at' => $now, 'resolved_by' => $actor->id, 'resolution_summary' => $summary];
                }
            }
            $this->changed($query);
            (new AuditRepository($this->pdo))->append($id, $actor->id, 'estado', ['estado' => $ticket['estado']], $after, $now);
        });
    }

    public function comment(int $actorId, int $id, int $version, string $content): int
    {
        return $this->operate($actorId, $id, $version, function (User $actor, array $ticket, array $users, string $now) use ($id, $version, $content): int {
            if (!AttentionPolicy::comment($actor, $ticket)) {
                throw new ValidationException(['contenido' => 'El ticket cerrado admite consulta únicamente.']);
            }
            $content = AttentionValidation::text($content, 'contenido', 1, 2000);
            $query = $this->pdo->prepare('INSERT INTO comentarios (ticket_id,autor_id,autor_rol,contenido,created_at) VALUES (?,?,?,?,?)');
            $query->execute([$id, $actor->id, $actor->role, $content, $now]);
            $commentId = (int) $this->pdo->lastInsertId();
            $first = $ticket['first_response_at'];
            if ($first === null && AttentionPolicy::firstResponse($actor, $ticket)) {
                $first = $now;
            }
            $query = $this->pdo->prepare('UPDATE tickets SET first_response_at=?,version=version+1 WHERE id=? AND version=?');
            $query->execute([$first, $id, $version]);
            $this->changed($query);
            // El comentario es inmutable y no se duplica como evento del historial.
            return $commentId;
        });
    }
}
