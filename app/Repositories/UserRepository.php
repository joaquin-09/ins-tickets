<?php

declare(strict_types=1);

namespace InsTickets\Repositories;

use InsTickets\Models\User;
use PDO;

final class UserRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function find(int $id): ?User
    {
        $query = $this->pdo->prepare('SELECT * FROM usuarios WHERE id=?');
        $query->execute([$id]);
        $row = $query->fetch();
        return $row === false ? null : User::fromRow($row);
    }

    public function byEmail(string $email): ?User
    {
        $query = $this->pdo->prepare('SELECT * FROM usuarios WHERE email=?');
        $query->execute([$email]);
        $row = $query->fetch();
        return $row === false ? null : User::fromRow($row);
    }

    public function page(int $page): array
    {
        $total = (int) $this->pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
        $pages = max(1, (int) ceil($total / 20));
        $page = min($page, $pages);
        $query = $this->pdo->prepare('SELECT id,nombre,email,rol,activo FROM usuarios ORDER BY id DESC LIMIT 20 OFFSET ?');
        $query->bindValue(1, ($page - 1) * 20, PDO::PARAM_INT);
        $query->execute();
        return ['rows' => $query->fetchAll(), 'page' => $page, 'pages' => $pages, 'total' => $total];
    }
}
