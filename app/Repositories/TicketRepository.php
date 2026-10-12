<?php

declare(strict_types=1);

namespace InsTickets\Repositories;

use InsTickets\Core\HttpException;
use InsTickets\Core\Input;
use InsTickets\Core\ValidationException;
use InsTickets\Models\User;
use InsTickets\Services\TicketValidation;
use PDO;

final class TicketRepository
{
    private const JOINS = ' FROM tickets t JOIN usuarios s ON s.id=t.solicitante_id LEFT JOIN usuarios a ON a.id=t.agente_id JOIN categorias c ON c.id=t.categoria_id ';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function scope(User $actor, string $mode = 'all'): array
    {
        if (!$actor->active) {
            return ['0=1', []];
        }
        if ($mode === 'assigned') {
            if ($actor->role !== 'agente') {
                throw new HttpException(403, 'Esta lista corresponde a agentes de soporte.');
            }
            return ['t.agente_id=?', [$actor->id]];
        }
        if ($mode === 'mine') {
            return ['t.solicitante_id=?', [$actor->id]];
        }
        if ($actor->role === 'administrador') {
            return ['1=1', []];
        }
        if ($actor->role === 'agente') {
            return ['(t.solicitante_id=? OR t.agente_id=?)', [$actor->id, $actor->id]];
        }
        return ['t.solicitante_id=?', [$actor->id]];
    }

    public static function filters(User $actor, array $input): array
    {
        $result = [];
        foreach (['q', 'estado', 'prioridad', 'categoria_id'] as $key) {
            $result[$key] = trim(Input::string($input, $key));
        }
        $errors = [];
        if (mb_strlen($result['q']) > 200) {
            $errors['q'] = 'La búsqueda admite hasta 200 caracteres.';
        }
        foreach (['estado' => TicketValidation::STATES, 'prioridad' => TicketValidation::PRIORITIES] as $key => $allowed) {
            if ($result[$key] !== '' && !isset($allowed[$result[$key]])) {
                $errors[$key] = 'Filtro no válido.';
            }
        }
        if ($actor->role === 'administrador') {
            foreach (['agente_id', 'solicitante_id'] as $key) {
                $result[$key] = trim(Input::string($input, $key));
            }
        }
        foreach (['categoria_id', 'agente_id', 'solicitante_id'] as $key) {
            $value = $result[$key] ?? '';
            if ($value !== '' && !($key === 'agente_id' && $value === 'sin_asignar') && filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                $errors[$key] = 'Filtro no válido.';
            }
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        return $result;
    }

    public function page(User $actor, string $mode, array $filters, int $page): array
    {
        [$scope, $params] = self::scope($actor, $mode);
        $where = [$scope];
        if (($filters['q'] ?? '') !== '') {
            $search = $filters['q'];
            $like = '%' . strtr($search, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
            $id = preg_match('/^(?:TCK-)?([0-9]+)$/iD', $search, $matches) ? filter_var(ltrim($matches[1], '0') ?: '0', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
            $where[] = "(t.id=? OR t.titulo LIKE ? ESCAPE '!' OR t.descripcion LIKE ? ESCAPE '!')";
            array_push($params, $id === false ? 0 : $id, $like, $like);
        }
        foreach (['estado', 'prioridad', 'categoria_id', 'agente_id', 'solicitante_id'] as $key) {
            if (($filters[$key] ?? '') === '') {
                continue;
            }
            if (in_array($key, ['agente_id', 'solicitante_id'], true) && $actor->role !== 'administrador') {
                continue;
            }
            if ($key === 'agente_id' && $filters[$key] === 'sin_asignar') {
                $where[] = 't.agente_id IS NULL';
            } else {
                $where[] = 't.' . $key . '=?';
                $params[] = str_ends_with($key, '_id') ? (int) $filters[$key] : $filters[$key];
            }
        }
        $sql = self::JOINS . ' WHERE ' . implode(' AND ', $where);
        $query = $this->pdo->prepare('SELECT COUNT(*)' . $sql);
        $query->execute($params);
        $total = (int) $query->fetchColumn();
        $pages = max(1, (int) ceil($total / 20));
        $page = min(max(1, $page), $pages);
        $query = $this->pdo->prepare('SELECT t.*,s.nombre AS solicitante_nombre,a.nombre AS agente_nombre,c.nombre AS categoria_nombre' . $sql . ' ORDER BY t.created_at DESC,t.id DESC LIMIT 20 OFFSET ?');
        foreach ([...$params, ($page - 1) * 20] as $position => $value) {
            $query->bindValue($position + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $query->execute();
        return ['rows' => $query->fetchAll(), 'total' => $total, 'pages' => $pages, 'page' => $page];
    }

    public function find(User $actor, int $id): ?array
    {
        [$scope, $params] = self::scope($actor);
        $query = $this->pdo->prepare('SELECT t.*,s.nombre AS solicitante_nombre,a.nombre AS agente_nombre,c.nombre AS categoria_nombre,c.activa AS categoria_activa,r.nombre AS resolutor_nombre,z.nombre AS cerrador_nombre' . self::JOINS . ' LEFT JOIN usuarios r ON r.id=t.resolved_by LEFT JOIN usuarios z ON z.id=t.closed_by WHERE (' . $scope . ') AND t.id=?');
        $query->execute([...$params, $id]);
        return $query->fetch() ?: null;
    }

    public function categories(bool $activeOnly = false, ?int $keep = null): array
    {
        $query = $this->pdo->prepare('SELECT id,nombre,activa FROM categorias' . ($activeOnly ? ' WHERE activa=1 OR id=?' : '') . ' ORDER BY nombre,id');
        $query->execute($activeOnly ? [$keep ?? 0] : []);
        return $query->fetchAll();
    }

    public function users(): array
    {
        return $this->pdo->query('SELECT id,nombre,rol,activo FROM usuarios ORDER BY nombre,id')->fetchAll();
    }

    public function comments(int $ticketId, int $page = 1): array
    {
        return $this->related('comentarios', 'autor_id', 'autor_nombre', $ticketId, $page);
    }

    public function history(int $ticketId, int $page = 1): array
    {
        return $this->related('historial_tickets', 'actor_id', 'actor_nombre', $ticketId, $page);
    }

    private function related(string $table, string $author, string $alias, int $id, int $page): array
    {
        // Tabla/columnas constantes de los dos métodos internos, jamás parámetros HTTP.
        $query = $this->pdo->prepare('SELECT COUNT(*) FROM ' . $table . ' WHERE ticket_id=?');
        $query->execute([$id]);
        $total = (int) $query->fetchColumn();
        $pages = max(1, (int) ceil($total / 20));
        $page = min(max(1, $page), $pages);
        $query = $this->pdo->prepare('SELECT x.*,u.nombre AS ' . $alias . ' FROM ' . $table . ' x JOIN usuarios u ON u.id=x.' . $author . ' WHERE x.ticket_id=? ORDER BY x.created_at,x.id LIMIT 20 OFFSET ?');
        $query->bindValue(1, $id, PDO::PARAM_INT);
        $query->bindValue(2, ($page - 1) * 20, PDO::PARAM_INT);
        $query->execute();
        return ['rows' => $query->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }
}
