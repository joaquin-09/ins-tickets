<?php

declare(strict_types=1);

namespace InsTickets\Tests\Integration;

use InsTickets\Core\Config;
use InsTickets\Core\Database;
use InsTickets\Core\Dates;
use InsTickets\Core\HttpException;
use InsTickets\Core\ValidationException;
use InsTickets\Repositories\TicketRepository;
use InsTickets\Repositories\UserRepository;
use InsTickets\Services\TicketService;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

final class TicketsTest extends TestCase
{
    private PDO $pdo;
    private PDO $maintenance;
    private array $users;
    private int $category;
    private int $inactive;

    protected function setUp(): void
    {
        if (getenv('APP_ENV') !== 'test' || getenv('DB_HOST') !== 'db-test') {
            throw new \RuntimeException('Se requiere el servidor aislado de pruebas.');
        }
        $this->maintenance = new PDO('mysql:host=db-test;dbname=ins_tickets;charset=utf8mb4','root',Config::get('TEST_ROOT_PASSWORD'),[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        foreach (['historial_tickets','comentarios','tickets','intentos_acceso','categorias','usuarios'] as $table) {
            $this->maintenance->exec('DELETE FROM '.$table);
        }
        $this->pdo = Database::connection();
        $query = $this->pdo->prepare('INSERT INTO usuarios (nombre,email,password_hash,rol) VALUES (?,?,?,?)');
        static $hash = null;
        $hash ??= password_hash('ClavePruebas2026!', PASSWORD_BCRYPT, ['cost' => 12]);
        foreach (['admin' => 'administrador','agent' => 'agente','requester' => 'usuario','other' => 'usuario'] as $key => $role) {
            $query->execute(['Demo '.$key,$key.'@nexoti.test',$hash,$role]);
            $this->users[$key] = (int) $this->pdo->lastInsertId();
        }
        $this->pdo->exec("INSERT INTO categorias (nombre) VALUES ('Hardware')");
        $this->category = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO categorias (nombre,activa) VALUES ('Histórica',0)");
        $this->inactive = (int) $this->pdo->lastInsertId();
    }

    private function data(array $changes = []): array
    {
        return array_replace(['titulo' => 'Incidencia de prueba','descripcion' => 'Descripción de prueba aislada suficiente','categoria_id' => (string) $this->category,'prioridad' => 'media'],$changes);
    }

    private function create(string $who = 'requester', array $changes = []): int
    {
        return (new TicketService($this->pdo))->create($this->users[$who],$this->data($changes),bin2hex(random_bytes(32)));
    }

    private function reject(callable $operation, string $class, ?int $status = null): void
    {
        try {
            $operation();
            self::fail('La operación debía rechazarse.');
        } catch (\Throwable $exception) {
            self::assertInstanceOf($class,$exception);
            if ($status !== null) { self::assertSame($status,$exception->status); }
        }
    }

    public function testCreateUsesSessionActorAndDuplicateDoesNotCreateEvents(): void
    {
        $service = new TicketService($this->pdo);
        $key = bin2hex(random_bytes(32));
        $id = $service->create($this->users['requester'],$this->data(['solicitante_id' => $this->users['other'],'estado' => 'cerrado','agente_id' => $this->users['agent']]),$key);
        self::assertSame($id,$service->create($this->users['requester'],$this->data(),$key));
        $ticket = $this->pdo->query('SELECT * FROM tickets')->fetch();
        self::assertSame($this->users['requester'],(int) $ticket['solicitante_id']);
        self::assertSame('abierto',$ticket['estado']);
        self::assertNull($ticket['agente_id']);
        self::assertNull($ticket['first_response_at']);
        self::assertSame(1,(int) $this->pdo->query('SELECT COUNT(*) FROM tickets')->fetchColumn());
        self::assertSame(1,(int) $this->pdo->query('SELECT COUNT(*) FROM historial_tickets')->fetchColumn());
        $this->create('agent'); $this->create('admin');
        self::assertSame(3,(int) $this->pdo->query('SELECT COUNT(*) FROM tickets')->fetchColumn());
    }

    public function testInvalidCreationAndInactiveCategoryLeaveNoPartialRecords(): void
    {
        $this->reject(fn () => $this->create('requester',['titulo' => ' ']),ValidationException::class);
        $this->reject(fn () => $this->create('requester',['categoria_id' => (string) $this->inactive]),ValidationException::class);
        self::assertSame(0,(int) $this->pdo->query('SELECT COUNT(*) FROM tickets')->fetchColumn());
        self::assertSame(0,(int) $this->pdo->query('SELECT COUNT(*) FROM historial_tickets')->fetchColumn());
        self::assertFalse($this->pdo->inTransaction());
    }

    public function testEditPreservesHistoricalInactiveCategoryAndAuditsActualChanges(): void
    {
        $id = $this->create();
        $this->pdo->exec('UPDATE categorias SET activa=0 WHERE id='.$this->category);
        $service = new TicketService($this->pdo);
        self::assertTrue($service->edit($this->users['requester'],$id,1,$this->data(['titulo' => 'Nuevo título de prueba'])));
        self::assertFalse($service->edit($this->users['requester'],$id,2,$this->data(['titulo' => 'Nuevo título de prueba'])));
        $this->reject(fn () => $service->edit($this->users['requester'],$id,2,$this->data(['categoria_id' => (string) $this->inactive])),ValidationException::class);
        self::assertSame(2,(int) $this->pdo->query('SELECT version FROM tickets')->fetchColumn());
        self::assertSame(2,(int) $this->pdo->query('SELECT COUNT(*) FROM historial_tickets')->fetchColumn());
    }

    public function testAssignedAgentCanClassifyButCannotEditTextAndOwnerCannotEditInProgress(): void
    {
        $id = $this->create();
        // Fixture de atención; no se declara implementado el flujo del incremento 4.
        $this->pdo->exec("UPDATE tickets SET agente_id=".$this->users['agent'].",estado='en_progreso',version=version+1 WHERE id=".$id);
        $service = new TicketService($this->pdo);
        $this->reject(fn () => $service->edit($this->users['agent'],$id,2,$this->data()),HttpException::class,403);
        self::assertTrue($service->edit($this->users['agent'],$id,2,['categoria_id' => (string) $this->category,'prioridad' => 'alta']));
        $this->reject(fn () => $service->edit($this->users['requester'],$id,3,$this->data()),HttpException::class,403);
        $this->reject(fn () => $service->edit($this->users['other'],$id,3,$this->data()),HttpException::class,404);
        self::assertSame('alta',$this->pdo->query('SELECT prioridad FROM tickets')->fetchColumn());
    }

    public function testVisibilitySearchCombinedFiltersAndPagination(): void
    {
        $first = 0;
        for ($i=0;$i<21;$i++) { $id = $this->create('requester',['titulo' => 'Solicitud número '.$i]); if ($i===0) {$first=$id;} }
        $other = $this->create('other'); $this->create('agent');
        $this->pdo->exec('UPDATE tickets SET agente_id='.$this->users['agent'].' WHERE id='.$first);
        $repo = new TicketRepository($this->pdo); $users = new UserRepository($this->pdo);
        $requester = $users->find($this->users['requester']); $agent = $users->find($this->users['agent']);
        $list = $repo->page($requester,'mine',[],1);
        self::assertSame(21,$list['total']); self::assertCount(20,$list['rows']); self::assertSame(2,$list['pages']);
        self::assertCount(1,$repo->page($requester,'mine',[],2)['rows']);
        self::assertNull($repo->find($requester,$other));
        $filters = TicketRepository::filters($requester,['q' => Dates::code($first),'prioridad' => 'media','estado' => 'abierto','categoria_id' => (string) $this->category]);
        self::assertSame(1,$repo->page($requester,'mine',$filters,1)['total']);
        self::assertSame(0,$repo->page($requester,'mine',['q' => "%' OR 1=1 --"],1)['total']);
        self::assertSame(2,$repo->page($agent,'all',[],1)['total']);
        self::assertSame(1,$repo->page($agent,'assigned',[],1)['total']);
        self::assertSame(1,$repo->page($agent,'mine',[],1)['total']);
    }

    public function testVersionConflictAndAuditFailureRollbackCreateAndEdit(): void
    {
        $id = $this->create(); $service = new TicketService($this->pdo);
        $service->edit($this->users['requester'],$id,1,$this->data(['titulo' => 'Segundo título de prueba']));
        $this->reject(fn () => $service->edit($this->users['requester'],$id,1,$this->data(['titulo' => 'Título obsoleto'])),HttpException::class,409);
        $this->maintenance->exec("CREATE TRIGGER fail_event03 BEFORE INSERT ON historial_tickets FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='controlled-test-failure'");
        try {
            $this->reject(fn () => $service->edit($this->users['requester'],$id,2,$this->data(['titulo' => 'No debe persistir'])),PDOException::class);
            $this->reject(fn () => $this->create(),PDOException::class);
            self::assertSame('Segundo título de prueba',$this->pdo->query('SELECT titulo FROM tickets')->fetchColumn());
            self::assertSame(2,(int) $this->pdo->query('SELECT version FROM tickets')->fetchColumn());
            self::assertSame(1,(int) $this->pdo->query('SELECT COUNT(*) FROM tickets')->fetchColumn());
            self::assertSame(2,(int) $this->pdo->query('SELECT COUNT(*) FROM historial_tickets')->fetchColumn());
        } finally { $this->maintenance->exec('DROP TRIGGER fail_event03'); }
    }
}
