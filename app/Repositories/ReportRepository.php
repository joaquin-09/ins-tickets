<?php

declare(strict_types=1);

namespace InsTickets\Repositories;

use InsTickets\Models\User;
use InsTickets\Services\ReportFilter;
use InsTickets\Services\ReportMetrics;
use InsTickets\Services\UserPolicy;
use PDO;

final class ReportRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function agentsForFilters(): array
    {
        // Conserva agentes históricos aunque hoy tengan otro rol o estén inactivos.
        return $this->pdo->query("SELECT u.id,u.nombre,u.rol,u.activo FROM usuarios u
            WHERE u.rol='agente' OR EXISTS (SELECT 1 FROM tickets t WHERE t.agente_id=u.id)
            ORDER BY u.nombre,u.id")->fetchAll();
    }

    public function panel(User $actor): array
    {
        [$where, $params] = TicketRepository::scope($actor);
        return $this->counts($where, $params);
    }

    public function report(User $actor, array $input, array $pages = []): array
    {
        UserPolicy::admin($actor);
        $filters = ReportFilter::validate($input);
        [$from, $until] = ReportFilter::utcBounds($filters);
        $where = ['1=1'];
        $params = [];
        if ($from !== null) { $where[] = 't.created_at>=?'; $params[] = $from; }
        if ($until !== null) { $where[] = 't.created_at<?'; $params[] = $until; }
        foreach (['estado', 'prioridad', 'categoria_id', 'agente_id'] as $key) {
            if ($filters[$key] === '') { continue; }
            if ($key === 'agente_id' && $filters[$key] === 'sin_asignar') {
                $where[] = 't.agente_id IS NULL';
            } else {
                $where[] = 't.' . $key . '=?';
                $params[] = str_ends_with($key, '_id') ? (int) $filters[$key] : $filters[$key];
            }
        }
        $where = implode(' AND ', $where);
        // Una vista consistente entre agregaciones, aun con atención concurrente.
        // No permite escribir ni altera el aislamiento de futuras transacciones.
        $this->pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
        $this->pdo->beginTransaction();
        try {
            $counts = $this->counts($where, $params);
            $query = $this->pdo->prepare("SELECT
                SUM(CASE WHEN first_response_at>=created_at THEN TIMESTAMPDIFF(MICROSECOND,created_at,first_response_at)/1000000 END) AS response_seconds,
                COUNT(CASE WHEN first_response_at>=created_at THEN 1 END) AS response_n,
                SUM(CASE WHEN estado IN ('resuelto','cerrado') AND resolved_at>=created_at THEN TIMESTAMPDIFF(MICROSECOND,created_at,resolved_at)/1000000 END) AS resolution_seconds,
                COUNT(CASE WHEN estado IN ('resuelto','cerrado') AND resolved_at>=created_at THEN 1 END) AS resolution_n
                FROM tickets t WHERE " . $where);
            $query->execute($params);
            $metrics = $query->fetch();
            $groups = [];
            foreach (['categorias', 'agentes', 'resolutores'] as $key) {
                $groups[$key] = $this->group($key, $where, $params, $pages[$key] ?? 1);
            }
            $this->pdo->commit();
            return ['filters' => $filters, 'counts' => $counts, 'groups' => $groups,
                'response' => ReportMetrics::mean($metrics['response_seconds'], (int) $metrics['response_n']),
                'resolution' => ReportMetrics::mean($metrics['resolution_seconds'], (int) $metrics['resolution_n'])];
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            throw $exception;
        }
    }

    private function counts(string $where, array $params): array
    {
        $query = $this->pdo->prepare("SELECT COUNT(*) AS total,
            COALESCE(SUM(estado='abierto'),0) AS abierto, COALESCE(SUM(estado='en_progreso'),0) AS en_progreso,
            COALESCE(SUM(estado='resuelto'),0) AS resuelto, COALESCE(SUM(estado='cerrado'),0) AS cerrado,
            COALESCE(SUM(prioridad='baja'),0) AS baja, COALESCE(SUM(prioridad='media'),0) AS media,
            COALESCE(SUM(prioridad='alta'),0) AS alta FROM tickets t WHERE " . $where);
        $query->execute($params);
        return array_map(static fn ($value): int => (int) $value, $query->fetch());
    }

    private function group(string $kind, string $where, array $params, int $page): array
    {
        // Columnas y joins son constantes internos, nunca nombres recibidos por HTTP.
        [$column, $join, $name, $extra] = match ($kind) {
            'categorias' => ['t.categoria_id', ' JOIN categorias g ON g.id=t.categoria_id', "CONCAT(g.nombre,IF(g.activa=0,' (inactiva)',''))", ''],
            'agentes' => ['t.agente_id', ' LEFT JOIN usuarios g ON g.id=t.agente_id', "COALESCE(g.nombre,'Sin asignar')", ''],
            'resolutores' => ['t.resolved_by', ' JOIN usuarios g ON g.id=t.resolved_by', 'g.nombre', " AND t.estado IN ('resuelto','cerrado') AND t.resolved_at IS NOT NULL"],
        };
        $sql = ' FROM tickets t' . $join . ' WHERE ' . $where . $extra;
        $query = $this->pdo->prepare('SELECT COUNT(*) FROM (SELECT ' . $column . $sql . ' GROUP BY ' . $column . ') grouped_tickets');
        $query->execute($params);
        $total = (int) $query->fetchColumn();
        $pages = max(1, (int) ceil($total / 20));
        $page = min(max(1, $page), $pages);
        $query = $this->pdo->prepare('SELECT ' . $column . ' AS id,' . $name . ' AS nombre,COUNT(*) AS cantidad' . $sql
            . ' GROUP BY ' . $column . ',g.nombre' . ($kind === 'categorias' ? ',g.activa' : '')
            . ' ORDER BY cantidad DESC,nombre,id LIMIT 20 OFFSET ?');
        foreach ([...$params, ($page - 1) * 20] as $position => $value) {
            $query->bindValue($position + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $query->execute();
        return ['rows' => $query->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }
}
