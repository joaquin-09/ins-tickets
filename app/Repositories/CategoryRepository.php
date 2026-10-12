<?php

declare(strict_types=1);

namespace InsTickets\Repositories;

use PDO;

final class CategoryRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function find(int $id): ?array
    {
        $query = $this->pdo->prepare('SELECT * FROM categorias WHERE id=?');
        $query->execute([$id]);
        return $query->fetch() ?: null;
    }

    public function page(int $page): array
    {
        $total = (int) $this->pdo->query('SELECT COUNT(*) FROM categorias')->fetchColumn();
        $pages = max(1, (int) ceil($total / 20));
        $page = min($page, $pages);
        $query = $this->pdo->prepare('SELECT * FROM categorias ORDER BY nombre,id LIMIT 20 OFFSET ?');
        $query->bindValue(1, ($page - 1) * 20, PDO::PARAM_INT);
        $query->execute();
        return ['rows' => $query->fetchAll(), 'page' => $page, 'pages' => $pages, 'total' => $total];
    }
}
