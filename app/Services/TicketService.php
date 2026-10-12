<?php

declare(strict_types=1);

namespace InsTickets\Services;

use InsTickets\Core\HttpException;
use InsTickets\Core\ValidationException;
use InsTickets\Models\User;
use InsTickets\Repositories\AuditRepository;
use PDO;

final class TicketService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    private function actor(int $id): User
    {
        // Orden común con administración; roles no pueden cambiar durante la escritura.
        $rows = $this->pdo->query('SELECT * FROM usuarios ORDER BY id FOR UPDATE')->fetchAll();
        foreach ($rows as $row) {
            if ((int) $row['id'] === $id && (bool) $row['activo']) {
                return User::fromRow($row);
            }
        }
        throw new HttpException(403, 'La cuenta ya no autoriza esta operación.');
    }

    private function category(int $id, ?int $current = null): void
    {
        $query = $this->pdo->prepare('SELECT activa FROM categorias WHERE id=? FOR UPDATE');
        $query->execute([$id]);
        $active = $query->fetchColumn();
        if ($active === false || (!(bool) $active && $id !== $current)) {
            throw new ValidationException(['categoria_id' => 'La categoría no está disponible. Podés conservar una categoría histórica inactiva, pero no asignarla nuevamente.']);
        }
    }

    public function create(int $actorId, array $input, string $key): int
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $key)) {
            throw new HttpException(409, 'La clave de envío no es válida.');
        }
        $this->pdo->beginTransaction();
        try {
            $this->actor($actorId);
            $existing = $this->pdo->prepare('SELECT id,solicitante_id FROM tickets WHERE submission_key=? FOR UPDATE');
            $existing->execute([$key]);
            if ($row = $existing->fetch()) {
                if ((int) $row['solicitante_id'] !== $actorId) {
                    throw new HttpException(409, 'La clave de envío no está disponible.');
                }
                $this->pdo->commit();
                return (int) $row['id'];
            }
            $data = TicketValidation::data($input, true);
            $this->category($data['categoria_id']);
            $query = $this->pdo->prepare('INSERT INTO tickets (solicitante_id,categoria_id,titulo,descripcion,prioridad,submission_key) VALUES (?,?,?,?,?,?)');
            $query->execute([$actorId, $data['categoria_id'], $data['titulo'], $data['descripcion'], $data['prioridad'], $key]);
            $id = (int) $this->pdo->lastInsertId();
            (new AuditRepository($this->pdo))->append($id, $actorId, 'creacion', null, $data + ['solicitante_id' => $actorId, 'estado' => 'abierto', 'agente_id' => null]);
            $this->pdo->commit();
            return $id;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function edit(int $actorId, int $id, int $version, array $input): bool
    {
        $this->pdo->beginTransaction();
        try {
            $actor = $this->actor($actorId);
            $query = $this->pdo->prepare('SELECT * FROM tickets WHERE id=? FOR UPDATE');
            $query->execute([$id]);
            $ticket = $query->fetch();
            if (!$ticket || !TicketPolicy::visible($actor, $ticket)) {
                throw new HttpException(404, 'No se encontró el ticket.');
            }
            if (!TicketPolicy::classification($actor, $ticket)) {
                throw new HttpException(403, 'No tenés permiso para editar este ticket en su estado actual.');
            }
            if ((int) $ticket['version'] !== $version) {
                throw new HttpException(409, 'El ticket cambió desde que abriste el formulario. Recargá antes de editar.');
            }
            $canText = TicketPolicy::text($actor, $ticket);
            if (!$canText && (array_key_exists('titulo', $input) || array_key_exists('descripcion', $input))) {
                throw new HttpException(403, 'Título y descripción no pueden modificarse con este permiso o estado.');
            }
            $data = TicketValidation::data($input, $canText);
            $this->category($data['categoria_id'], (int) $ticket['categoria_id']);
            $before = [];
            $after = [];
            foreach ($data as $field => $value) {
                $old = $field === 'categoria_id' ? (int) $ticket[$field] : $ticket[$field];
                if ($old !== $value) {
                    $before[$field] = $old;
                    $after[$field] = $value;
                }
            }
            if ($after === []) {
                $this->pdo->commit();
                return false;
            }
            $title = $data['titulo'] ?? $ticket['titulo'];
            $description = $data['descripcion'] ?? $ticket['descripcion'];
            $query = $this->pdo->prepare('UPDATE tickets SET titulo=?,descripcion=?,categoria_id=?,prioridad=?,version=version+1 WHERE id=? AND version=?');
            $query->execute([$title, $description, $data['categoria_id'], $data['prioridad'], $id, $version]);
            if ($query->rowCount() !== 1) {
                throw new HttpException(409, 'El ticket cambió. Recargá la página.');
            }
            (new AuditRepository($this->pdo))->append($id, $actorId, 'edicion', $before, $after);
            $this->pdo->commit();
            return true;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }
}
