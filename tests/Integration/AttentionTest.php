<?php

declare(strict_types=1);

namespace InsTickets\Tests\Integration;

use InsTickets\Core\Config;
use InsTickets\Core\Database;
use InsTickets\Core\HttpException;
use InsTickets\Core\ValidationException;
use InsTickets\Repositories\TicketRepository;
use InsTickets\Repositories\UserRepository;
use InsTickets\Services\AttentionService;
use InsTickets\Services\TicketService;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

final class AttentionTest extends TestCase
{
    private PDO $pdo;
    private PDO $maintenance;
    private array $users = [];
    private int $category;
    private AttentionService $service;

    protected function setUp(): void
    {
        if (getenv('APP_ENV') !== 'test' || getenv('DB_HOST') !== 'db-test') {
            throw new \RuntimeException('Solo se permite el servidor aislado de pruebas.');
        }
        $this->maintenance = new PDO('mysql:host=db-test;dbname=ins_tickets;charset=utf8mb4','root',Config::get('TEST_ROOT_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        foreach (['historial_tickets','comentarios','tickets','intentos_acceso','categorias','usuarios'] as $table) {
            $this->maintenance->exec('DELETE FROM '.$table);
        }
        $this->pdo = Database::connection();
        static $hash = null;
        $hash ??= password_hash('ClavePruebas2026!',PASSWORD_BCRYPT,['cost'=>12]);
        $query = $this->pdo->prepare('INSERT INTO usuarios (nombre,email,password_hash,rol) VALUES (?,?,?,?)');
        foreach (['admin'=>'administrador','agent1'=>'agente','agent2'=>'agente','requester'=>'usuario','other'=>'usuario'] as $key=>$role) {
            $query->execute(['Demo '.$key,$key.'@nexoti.test',$hash,$role]);
            $this->users[$key] = (int) $this->pdo->lastInsertId();
        }
        $this->pdo->exec("INSERT INTO categorias (nombre) VALUES ('Hardware')");
        $this->category = (int) $this->pdo->lastInsertId();
        $this->service = new AttentionService($this->pdo);
    }

    private function create(string $owner='requester'): int
    {
        return (new TicketService($this->pdo))->create($this->users[$owner],['titulo'=>'Incidencia para atención','descripcion'=>'Descripción suficiente del fixture aislado','categoria_id'=>(string) $this->category,'prioridad'=>'media'],bin2hex(random_bytes(32)));
    }

    private function ticket(int $id): array
    {
        $query=$this->pdo->prepare('SELECT * FROM tickets WHERE id=?');$query->execute([$id]);return $query->fetch();
    }

    private function assign(int $id,string $agent='agent1'): void
    {
        $this->service->assign($this->users['admin'],$id,(int) $this->ticket($id)['version'],$this->users[$agent]);
    }

    private function change(int $id,string $actor,string $state,string $summary=''): void
    {
        $this->service->transition($this->users[$actor],$id,(int) $this->ticket($id)['version'],$state,$summary);
    }

    private function comment(int $id,string $actor,string $content='Comentario de prueba'): void
    {
        $this->service->comment($this->users[$actor],$id,(int) $this->ticket($id)['version'],$content);
    }

    private function reject(callable $operation,string $class,?int $status=null): void
    {
        try {$operation();self::fail('La operación debía ser rechazada.');}
        catch (\Throwable $exception) {self::assertInstanceOf($class,$exception);if($status!==null){self::assertSame($status,$exception->status);}}
    }

    public function testFullFlowAuthorSnapshotFirstResponseAndClosure(): void
    {
        $id=$this->create();
        $this->comment($id,'requester');self::assertNull($this->ticket($id)['first_response_at']);
        $this->assign($id);self::assertNull($this->ticket($id)['first_response_at']);
        $this->change($id,'agent1','en_progreso');self::assertNull($this->ticket($id)['first_response_at']);
        $this->comment($id,'agent1');$first=$this->ticket($id)['first_response_at'];self::assertNotNull($first);
        $this->comment($id,'admin');self::assertSame($first,$this->ticket($id)['first_response_at']);
        $this->change($id,'agent1','resuelto','Se corrigió la causa de la incidencia.');
        self::assertSame(3,(int) $this->pdo->query('SELECT COUNT(*) FROM comentarios')->fetchColumn());
        $this->comment($id,'requester','Gracias por la atención recibida.');
        $this->change($id,'requester','cerrado');$ticket=$this->ticket($id);
        self::assertSame('cerrado',$ticket['estado']);self::assertSame($this->users['agent1'],(int) $ticket['resolved_by']);
        self::assertSame($this->users['requester'],(int) $ticket['closed_by']);self::assertSame($first,$ticket['first_response_at']);
        self::assertGreaterThanOrEqual($ticket['resolved_at'],$ticket['closed_at']);
        self::assertSame(5,(int) $this->pdo->query('SELECT COUNT(*) FROM historial_tickets')->fetchColumn());
        self::assertSame('usuario',$this->pdo->query('SELECT autor_rol FROM comentarios ORDER BY id LIMIT 1')->fetchColumn());
        $this->reject(fn()=> $this->comment($id,'admin'),ValidationException::class);
        $this->reject(fn()=> $this->comment($id,'other'),HttpException::class,404);
    }

    public function testReassignmentPreservesTimesHistoryAndLosesFormerAgentAccess(): void
    {
        $id=$this->create();$this->assign($id);$this->change($id,'agent1','en_progreso');$this->comment($id,'agent1');
        $before=$this->ticket($id);$this->assign($id,'agent2');$after=$this->ticket($id);
        self::assertSame('en_progreso',$after['estado']);self::assertSame($before['first_response_at'],$after['first_response_at']);
        self::assertSame($before['created_at'],$after['created_at']);
        $repo=new TicketRepository($this->pdo);$users=new UserRepository($this->pdo);
        self::assertNull($repo->find($users->find($this->users['agent1']),$id));
        self::assertNotNull($repo->find($users->find($this->users['agent2']),$id));
        self::assertSame($this->users['agent1'],(int) $this->pdo->query('SELECT autor_id FROM comentarios')->fetchColumn());
        $this->reject(fn()=> $this->service->assign($this->users['admin'],$id,(int)$after['version'],null),ValidationException::class);
        $this->reject(fn()=> $this->assign($id,'agent2'),ValidationException::class);
        $this->reject(fn()=> $this->change($id,'agent1','resuelto','Solución no autorizada.'),HttpException::class,404);
    }

    public function testAssignmentCandidatesAndAdministrativePermission(): void
    {
        $id=$this->create('agent1');
        $this->reject(fn()=> $this->assign($id,'agent1'),ValidationException::class);
        $this->reject(fn()=> $this->service->assign($this->users['agent1'],$id,1,$this->users['agent2']),HttpException::class,403);
        $this->reject(fn()=> $this->service->assign($this->users['admin'],$id,1,$this->users['requester']),ValidationException::class);
        $this->pdo->exec('UPDATE usuarios SET activo=0 WHERE id='.$this->users['agent2']);
        $this->reject(fn()=> $this->assign($id,'agent2'),ValidationException::class);
        self::assertNull($this->ticket($id)['agente_id']);
        self::assertSame(1,(int)$this->ticket($id)['version']);
    }

    public function testOwnAdministratorTicketMustBeAttendedBySomeoneElse(): void
    {
        $id=$this->create('admin');$this->assign($id);
        $this->reject(fn()=> $this->change($id,'admin','en_progreso'),HttpException::class,403);
        $this->comment($id,'admin');self::assertNull($this->ticket($id)['first_response_at']);
        $this->change($id,'agent1','en_progreso');
        $this->reject(fn()=> $this->change($id,'admin','resuelto','No debe autorresolver.'),HttpException::class,403);
        $this->change($id,'agent1','resuelto','La otra persona realizó la corrección.');$this->change($id,'admin','cerrado');
        self::assertSame($this->users['admin'],(int)$this->ticket($id)['closed_by']);
    }

    public function testInvalidTransitionsSummaryAndCommentsDoNotMutate(): void
    {
        $id=$this->create();
        $this->reject(fn()=> $this->change($id,'admin','en_progreso'),ValidationException::class);
        $this->assign($id);
        $this->reject(fn()=> $this->change($id,'agent1','resuelto','No saltar estados.'),ValidationException::class);
        $this->reject(fn()=> $this->change($id,'requester','cerrado'),ValidationException::class);
        $this->reject(fn()=> $this->comment($id,'requester',' '),ValidationException::class);
        $this->change($id,'agent1','en_progreso');
        $this->reject(fn()=> $this->change($id,'agent1','en_progreso'),ValidationException::class);
        $this->reject(fn()=> $this->change($id,'agent1','resuelto','corta'),ValidationException::class);
        $this->reject(fn()=> $this->change($id,'admin','abierto'),ValidationException::class);
        self::assertSame('en_progreso',$this->ticket($id)['estado']);self::assertNull($this->ticket($id)['resolved_at']);
        self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM comentarios')->fetchColumn());
    }

    public function testResolutionWithoutResponseAndAdminAttribution(): void
    {
        $id=$this->create();$this->assign($id);$this->change($id,'admin','en_progreso');
        $this->change($id,'admin','resuelto','Se resolvió sin comentario de soporte previo.');
        self::assertNull($this->ticket($id)['first_response_at']);self::assertNotNull($this->ticket($id)['resolved_at']);
        self::assertSame($this->users['admin'],(int)$this->ticket($id)['resolved_by']);
        $this->comment($id,'agent1');self::assertNull($this->ticket($id)['first_response_at']);
        $this->reject(fn()=> $this->change($id,'agent1','cerrado'),HttpException::class,403);
        $this->reject(fn()=> $this->assign($id,'agent2'),ValidationException::class);
        $this->change($id,'requester','cerrado');self::assertSame($this->users['admin'],(int)$this->ticket($id)['resolved_by']);
    }

    public function testAuditAndTicketFailuresRollBackAllAffectedTables(): void
    {
        $id=$this->create();
        $this->maintenance->exec("CREATE TRIGGER fail_event04 BEFORE INSERT ON historial_tickets FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='controlled-test-failure'");
        try {
            $this->reject(fn()=> $this->assign($id),PDOException::class);
            self::assertNull($this->ticket($id)['agente_id']);self::assertSame(1,(int)$this->ticket($id)['version']);
        } finally {$this->maintenance->exec('DROP TRIGGER fail_event04');}
        $this->assign($id);$this->change($id,'agent1','en_progreso');$before=$this->ticket($id);
        $this->maintenance->exec("CREATE TRIGGER fail_state04 BEFORE INSERT ON historial_tickets FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='controlled-test-failure'");
        try {
            $this->reject(fn()=> $this->change($id,'agent1','resuelto','No debe quedar resuelto parcialmente.'),PDOException::class);
            self::assertSame('en_progreso',$this->ticket($id)['estado']);self::assertNull($this->ticket($id)['resolved_at']);
        } finally {$this->maintenance->exec('DROP TRIGGER fail_state04');}
        $this->maintenance->exec("CREATE TRIGGER fail_response04 BEFORE UPDATE ON tickets FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='controlled-test-failure'");
        try {
            $this->reject(fn()=> $this->comment($id,'agent1'),PDOException::class);
            self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM comentarios')->fetchColumn());
            self::assertNull($this->ticket($id)['first_response_at']);self::assertSame((int)$before['version'],(int)$this->ticket($id)['version']);
        } finally {$this->maintenance->exec('DROP TRIGGER fail_response04');}
    }

    public function testConcurrentWriterWaitsAndRejectsStaleVersion(): void
    {
        $id=$this->create();$process=null;$pipes=[];
        try {
            $this->maintenance->beginTransaction();
            $this->maintenance->query('SELECT id FROM usuarios ORDER BY id FOR UPDATE')->fetchAll();
            $this->maintenance->exec('UPDATE tickets SET version=version+1 WHERE id='.$id);
            $process=proc_open([PHP_BINARY,dirname(__DIR__).'/support/attention-worker.php',(string)$this->users['requester'],(string)$id,'1'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__,2));
            self::assertIsResource($process);fclose($pipes[0]);stream_set_timeout($pipes[1],10);
            self::assertSame("READY\n",fgets($pipes[1]));
            $waiting=false;
            for($i=0;$i<50;$i++){
                if((int)$this->maintenance->query('SELECT COUNT(*) FROM performance_schema.data_lock_waits')->fetchColumn()>0){$waiting=true;break;}
                usleep(100000);
            }
            self::assertTrue($waiting,'El segundo proceso debe esperar el bloqueo InnoDB antes de liberar la primera transacción.');
            stream_set_blocking($pipes[1],false);
            self::assertSame('',stream_get_contents($pipes[1]));
            $this->maintenance->commit();stream_set_blocking($pipes[1],true);stream_set_timeout($pipes[1],10);
            $output=stream_get_contents($pipes[1]);$result=json_decode(trim($output),true,512,JSON_THROW_ON_ERROR);
            self::assertSame(409,$result['status']);
            self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM comentarios')->fetchColumn());
            self::assertSame(2,(int)$this->ticket($id)['version']);
            self::assertSame(0,proc_close($process));$process=null;
        } finally {
            if($this->maintenance->inTransaction()){$this->maintenance->rollBack();}
            foreach($pipes as $pipe){if(is_resource($pipe)){fclose($pipe);}}
            if(is_resource($process)){proc_terminate($process);proc_close($process);}
        }
    }
}
