<?php

declare(strict_types=1);

namespace InsTickets\Tests\Integration;

use InsTickets\Core\Config;
use InsTickets\Core\Database;
use InsTickets\Core\HttpException;
use InsTickets\Core\ValidationException;
use InsTickets\Models\User;
use InsTickets\Repositories\ReportRepository;
use InsTickets\Repositories\UserRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class ReportsTest extends TestCase
{
    private PDO $pdo;
    private PDO $maintenance;
    private array $users = [];
    private int $category;
    private int $historical;

    protected function setUp(): void
    {
        if (getenv('APP_ENV') !== 'test' || getenv('DB_HOST') !== 'db-test') {
            throw new \RuntimeException('Se requiere el servidor aislado de pruebas.');
        }
        $this->maintenance = new PDO('mysql:host=db-test;dbname=ins_tickets;charset=utf8mb4', 'root', Config::get('TEST_ROOT_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        foreach (['historial_tickets', 'comentarios', 'tickets', 'intentos_acceso', 'categorias', 'usuarios'] as $table) { $this->maintenance->exec('DELETE FROM ' . $table); }
        $this->pdo = Database::connection();
        $query = $this->pdo->prepare('INSERT INTO usuarios (nombre,email,password_hash,rol) VALUES (?,?,?,?)');
        static $hash = null;
        $hash ??= password_hash('ClavePruebas2026!', PASSWORD_BCRYPT, ['cost' => 12]);
        foreach (['admin' => 'administrador', 'agent' => 'agente', 'requester' => 'usuario', 'other' => 'usuario'] as $key => $role) {
            $query->execute(['Demo ' . $key, $key . '@nexoti.test', $hash, $role]);
            $this->users[$key] = (int) $this->pdo->lastInsertId();
        }
        $this->pdo->exec("INSERT INTO categorias (nombre) VALUES ('Hardware')");
        $this->category = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO categorias (nombre,activa) VALUES ('Histórica',0)");
        $this->historical = (int) $this->pdo->lastInsertId();
        // Dataset sintético, no medido. Día local 12/10/2026 y fechas UTC.
        $this->ticket('resuelto', 'alta', '2026-10-12 12:00:00', '2026-10-12 13:00:00', '2026-10-12 16:00:00');
        $this->ticket('cerrado', 'media', '2026-10-12 12:00:00', '2026-10-12 15:00:00', '2026-10-12 20:00:00', true);
        $this->ticket('abierto', 'baja', '2026-10-12 12:00:00');
        $this->ticket('en_progreso', 'alta', '2026-10-12 12:00:00');
    }

    private function actor(string $key): User
    {
        return (new UserRepository($this->pdo))->find($this->users[$key]);
    }

    private function ticket(string $state, string $priority, string $created, ?string $first = null, ?string $resolved = null, bool $historic = false, string $requester = 'requester', ?int $category = null): int
    {
        $query = $this->pdo->prepare('INSERT INTO tickets (solicitante_id,agente_id,categoria_id,titulo,descripcion,prioridad,estado,first_response_at,resolved_at,resolved_by,resolution_summary,closed_at,closed_by,submission_key,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $query->execute([$this->users[$requester], $state === 'abierto' ? null : $this->users['agent'], $category ?? ($historic ? $this->historical : $this->category),
            'Solicitud sintética de reporte', 'Descripción controlada para prueba aislada', $priority, $state, $first, $resolved,
            $resolved === null ? null : $this->users[$historic ? 'admin' : 'agent'], $resolved === null ? null : 'Solución sintética de prueba',
            $state === 'cerrado' ? '2026-10-12 21:00:00' : null, $state === 'cerrado' ? $this->users[$requester] : null,
            bin2hex(random_bytes(32)), $created]);
        return (int) $this->pdo->lastInsertId();
    }

    public function testKnownDatasetCountsMeansAndDifferentAttributions(): void
    {
        $report = (new ReportRepository($this->pdo))->report($this->actor('admin'), []);
        self::assertSame(['total' => 4, 'abierto' => 1, 'en_progreso' => 1, 'resuelto' => 1, 'cerrado' => 1, 'baja' => 1, 'media' => 1, 'alta' => 2], $report['counts']);
        self::assertSame('2.00 h', $report['response']['label']); self::assertSame(2, $report['response']['n']);
        self::assertSame('6.00 h', $report['resolution']['label']); self::assertSame(2, $report['resolution']['n']);
        $assigned = array_column($report['groups']['agentes']['rows'], 'cantidad', 'id');
        self::assertSame(3, (int) $assigned[$this->users['agent']]); self::assertSame(1, (int) $assigned['']);
        $resolved = array_column($report['groups']['resolutores']['rows'], 'cantidad', 'id');
        self::assertSame(1, (int) $resolved[$this->users['agent']]); self::assertSame(1, (int) $resolved[$this->users['admin']]);
        self::assertStringContainsString('(inactiva)', implode(',', array_column($report['groups']['categorias']['rows'], 'nombre')));
        self::assertFalse($this->pdo->inTransaction());
    }

    public function testFiltersAffectAllFiguresAndResolvedWithoutResponseIsExcludedOnlyFromResponse(): void
    {
        $repo = new ReportRepository($this->pdo);
        $input = ['desde' => '2026-10-12', 'hasta' => '2026-10-12', 'estado' => 'cerrado', 'prioridad' => 'media', 'categoria_id' => (string) $this->historical, 'agente_id' => (string) $this->users['agent']];
        $report = $repo->report($this->actor('admin'), $input);
        self::assertSame(1, $report['counts']['total']); self::assertSame(1, $report['counts']['cerrado']);
        self::assertSame('3.00 h', $report['response']['label']); self::assertSame('8.00 h', $report['resolution']['label']);
        foreach ($report['groups'] as $group) { self::assertCount(1, $group['rows']); self::assertSame(1, (int) $group['rows'][0]['cantidad']); }
        self::assertSame($this->users['admin'], (int) $report['groups']['resolutores']['rows'][0]['id']);
        $this->ticket('resuelto', 'alta', '2026-10-12 12:00:00', null, '2026-10-12 14:00:00');
        $report = $repo->report($this->actor('admin'), []);
        self::assertSame(2, $report['response']['n']); self::assertSame(3, $report['resolution']['n']);
        self::assertSame('4.67 h', $report['resolution']['label']);
        $unassigned = $repo->report($this->actor('admin'), ['agente_id' => 'sin_asignar']);
        self::assertSame(1, $unassigned['counts']['total']); self::assertSame('Sin datos', $unassigned['response']['label']);
        self::assertSame('Sin datos', $unassigned['resolution']['label']); self::assertSame([], $unassigned['groups']['resolutores']['rows']);
    }

    public function testLocalDateBoundaryUsesCreationNotResolutionAndEmptyReportHasNoFakeMean(): void
    {
        $this->ticket('abierto', 'baja', '2026-10-12 05:59:59.999999');
        $this->ticket('abierto', 'baja', '2026-10-12 06:00:00.000000');
        $this->ticket('abierto', 'baja', '2026-10-13 05:59:59.999999');
        $this->ticket('abierto', 'baja', '2026-10-13 06:00:00.000000');
        $this->ticket('resuelto', 'media', '2026-10-11 12:00:00', null, '2026-10-12 16:00:00');
        $repo = new ReportRepository($this->pdo);
        $report = $repo->report($this->actor('admin'), ['desde' => '2026-10-12', 'hasta' => '2026-10-12']);
        self::assertSame(6, $report['counts']['total']); self::assertSame(3, $report['counts']['abierto']);
        self::assertSame(2, $report['resolution']['n']);
        $empty = $repo->report($this->actor('admin'), ['desde' => '2027-01-01']);
        self::assertSame(0, $empty['counts']['total']); self::assertSame(0, $empty['response']['n']);
        self::assertNull($empty['resolution']['hours']); self::assertSame('Sin datos', $empty['resolution']['label']);
        foreach ($empty['groups'] as $group) { self::assertSame([], $group['rows']); self::assertSame(0, $group['total']); }
    }

    public function testPanelsHaveAuthorizedScopeAndFullReportsRejectOtherRoles(): void
    {
        $this->ticket('abierto', 'media', '2026-10-12 12:00:00', requester: 'agent');
        $this->ticket('abierto', 'media', '2026-10-12 12:00:00', requester: 'other');
        $repo = new ReportRepository($this->pdo);
        self::assertSame(6, $repo->panel($this->actor('admin'))['total']);
        self::assertSame(4, $repo->panel($this->actor('agent'))['total']);
        self::assertSame(4, $repo->panel($this->actor('requester'))['total']);
        self::assertSame(1, $repo->panel($this->actor('other'))['total']);
        foreach (['agent', 'requester'] as $key) {
            try { $repo->report($this->actor($key), []); self::fail('Debe denegar reportes globales.'); }
            catch (HttpException $exception) { self::assertSame(403, $exception->status); }
        }
        $inactive = new User($this->users['admin'], 'Inactivo', 'inactive@nexoti.test', 'administrador', false, '');
        self::assertSame(0, $repo->panel($inactive)['total']);
        $this->expectException(HttpException::class); $repo->report($inactive, []);
    }

    public function testGroupsPaginateAndInvalidFiltersDoNotLeaveOpenTransaction(): void
    {
        for ($i = 0; $i < 21; $i++) {
            $query = $this->pdo->prepare('INSERT INTO categorias (nombre) VALUES (?)'); $query->execute(['Grupo ' . $i]);
            $this->ticket('abierto', 'media', '2026-10-12 12:00:00', category: (int) $this->pdo->lastInsertId());
        }
        $repo = new ReportRepository($this->pdo);
        $first = $repo->report($this->actor('admin'), [], ['categorias' => 1]);
        $second = $repo->report($this->actor('admin'), [], ['categorias' => 2]);
        self::assertSame(23, $first['groups']['categorias']['total']); self::assertCount(20, $first['groups']['categorias']['rows']);
        self::assertCount(3, $second['groups']['categorias']['rows']);
        self::assertSame(25, $second['counts']['total']); self::assertSame($first['response'], $second['response']);
        try { $repo->report($this->actor('admin'), ['desde' => "2026-10-12' OR 1=1"]); self::fail('Debe validar.'); }
        catch (ValidationException) { self::assertFalse($this->pdo->inTransaction()); }
        // Deja solo asignaciones históricas antes de cambiar el rol del agente.
        $query = $this->pdo->prepare("UPDATE tickets SET estado='resuelto',resolved_at='2026-10-12 22:00:00',resolved_by=?,resolution_summary='Fixture de resolución para rol histórico' WHERE estado='en_progreso' AND agente_id=?");
        $query->execute([$this->users['admin'], $this->users['agent']]);
        $this->pdo->exec('UPDATE usuarios SET activo=0,rol=\'usuario\' WHERE id=' . $this->users['agent']);
        self::assertContains($this->users['agent'], array_map('intval', array_column($repo->agentsForFilters(), 'id')));
    }
}
