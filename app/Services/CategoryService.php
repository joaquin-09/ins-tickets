<?php

declare(strict_types=1);

namespace InsTickets\Services;

use InsTickets\Core\HttpException;
use InsTickets\Core\ValidationException;
use InsTickets\Models\User;
use PDO;
use PDOException;

final class CategoryService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    private function checkAdmin(int $id): void
    {
        $query = $this->pdo->prepare('SELECT * FROM usuarios WHERE id=? FOR UPDATE');
        $query->execute([$id]);
        $row = $query->fetch();
        UserPolicy::admin($row ? User::fromRow($row) : null);
    }

    public function save(int $actorId, ?int $id, array $input): int
    {
        $data = Validation::category($input);
        $this->pdo->beginTransaction();
        try {
            $this->checkAdmin($actorId);
            if ($id !== null) {
                $query = $this->pdo->prepare('SELECT id FROM categorias WHERE id=? FOR UPDATE');
                $query->execute([$id]);
                if (!$query->fetchColumn()) {
                    throw new HttpException(404, 'No se encontró la categoría.');
                }
                $query = $this->pdo->prepare('UPDATE categorias SET nombre=?,descripcion=?,activa=? WHERE id=?');
                $query->execute([$data['nombre'], $data['descripcion'], $data['activa'], $id]);
            } else {
                $query = $this->pdo->prepare('INSERT INTO categorias (nombre,descripcion,activa) VALUES (?,?,?)');
                $query->execute([$data['nombre'], $data['descripcion'], $data['activa']]);
                $id = (int) $this->pdo->lastInsertId();
            }
            $this->pdo->commit();
            return $id;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($exception instanceof PDOException && ($exception->errorInfo[1] ?? 0) === 1062) {
                throw new ValidationException(['nombre' => 'Ese nombre de categoría ya existe.']);
            }
            throw $exception;
        }
    }

    public function delete(int $actorId, int $id): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->checkAdmin($actorId);
            $query = $this->pdo->prepare('SELECT id FROM categorias WHERE id=? FOR UPDATE');
            $query->execute([$id]);
            if (!$query->fetchColumn()) {
                throw new HttpException(404, 'No se encontró la categoría.');
            }
            $query = $this->pdo->prepare('SELECT COUNT(*) FROM tickets WHERE categoria_id=?');
            $query->execute([$id]);
            if ((int) $query->fetchColumn() > 0) {
                throw new ValidationException(['general' => 'La categoría tiene tickets históricos. Podés desactivarla para conservarlos.']);
            }
            $query = $this->pdo->prepare('DELETE FROM categorias WHERE id=?');
            $query->execute([$id]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($exception instanceof PDOException && ($exception->errorInfo[1] ?? 0) === 1451) {
                throw new ValidationException(['general' => 'La categoría tiene referencias y no puede eliminarse.']);
            }
            throw $exception;
        }
    }
}
