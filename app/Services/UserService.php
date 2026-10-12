<?php

declare(strict_types=1);

namespace InsTickets\Services;

use InsTickets\Core\HttpException;
use InsTickets\Core\ValidationException;
use InsTickets\Models\User;
use PDO;
use PDOException;

final class UserService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** Bloqueo en orden de ID: serializa cambios de roles y protege al último admin. */
    private function lockedUsers(): array
    {
        $users = [];
        foreach ($this->pdo->query('SELECT * FROM usuarios ORDER BY id FOR UPDATE')->fetchAll() as $row) {
            $users[(int) $row['id']] = User::fromRow($row);
        }
        return $users;
    }

    private function pending(int $id): int
    {
        $query = $this->pdo->prepare("SELECT id FROM tickets WHERE agente_id=? AND estado IN ('abierto','en_progreso') FOR UPDATE");
        $query->execute([$id]);
        return count($query->fetchAll());
    }

    public function save(int $actorId, ?int $targetId, array $input): int
    {
        $data = Validation::user($input, $targetId === null);
        $this->pdo->beginTransaction();
        try {
            $users = $this->lockedUsers();
            $actor = $users[$actorId] ?? null;
            UserPolicy::admin($actor);
            if ($targetId !== null) {
                $target = $users[$targetId] ?? throw new HttpException(404, 'No se encontró la cuenta.');
                $admins = count(array_filter($users, static fn (User $user): bool => $user->active && $user->role === 'administrador'));
                UserPolicy::change($actor, $target, $data['rol'], (bool) $data['activo'], $admins, $this->pending($targetId));
                if ($actorId === $targetId && $data['password'] !== '') {
                    throw new ValidationException(['password' => 'Para cambiar tu propia contraseña, usá Mi contraseña y verificá la actual.']);
                }
                $hash = $data['password'] === '' ? $target->passwordHash() : password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
                $query = $this->pdo->prepare('UPDATE usuarios SET nombre=?,email=?,rol=?,activo=?,password_hash=? WHERE id=?');
                $query->execute([$data['nombre'], $data['email'], $data['rol'], $data['activo'], $hash, $targetId]);
            } else {
                $query = $this->pdo->prepare('INSERT INTO usuarios (nombre,email,rol,activo,password_hash) VALUES (?,?,?,?,?)');
                $query->execute([$data['nombre'], $data['email'], $data['rol'], $data['activo'], password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12])]);
                $targetId = (int) $this->pdo->lastInsertId();
            }
            $this->pdo->commit();
            return $targetId;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($exception instanceof PDOException && ($exception->errorInfo[1] ?? 0) === 1062) {
                throw new ValidationException(['email' => 'Ese correo ya está registrado.']);
            }
            throw $exception;
        }
    }

    public function delete(int $actorId, int $targetId): void
    {
        $this->pdo->beginTransaction();
        try {
            $users = $this->lockedUsers();
            $actor = $users[$actorId] ?? null;
            UserPolicy::admin($actor);
            $target = $users[$targetId] ?? throw new HttpException(404, 'No se encontró la cuenta.');
            $admins = count(array_filter($users, static fn (User $user): bool => $user->active && $user->role === 'administrador'));
            UserPolicy::change($actor, $target, $target->role, $target->active, $admins, $this->pending($targetId), true);
            $query = $this->pdo->prepare('SELECT (SELECT COUNT(*) FROM tickets WHERE solicitante_id=? OR agente_id=? OR resolved_by=? OR closed_by=?) + (SELECT COUNT(*) FROM comentarios WHERE autor_id=?) + (SELECT COUNT(*) FROM historial_tickets WHERE actor_id=?)');
            $query->execute(array_fill(0, 6, $targetId));
            if ((int) $query->fetchColumn() > 0) {
                throw new ValidationException(['general' => 'La cuenta tiene referencias históricas. Podés desactivarla cuando no tenga tickets pendientes.']);
            }
            $query = $this->pdo->prepare('DELETE FROM usuarios WHERE id=?');
            $query->execute([$targetId]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($exception instanceof PDOException && ($exception->errorInfo[1] ?? 0) === 1451) {
                throw new ValidationException(['general' => 'La cuenta tiene referencias y no puede eliminarse.']);
            }
            throw $exception;
        }
    }
}
