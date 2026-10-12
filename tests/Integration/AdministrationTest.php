<?php

declare(strict_types=1);

namespace InsTickets\Tests\Integration;

use InsTickets\Core\Config;
use InsTickets\Core\Database;
use InsTickets\Core\HttpException;
use InsTickets\Core\ValidationException;
use InsTickets\Repositories\UserRepository;
use InsTickets\Services\AuthService;
use InsTickets\Services\CategoryService;
use InsTickets\Services\LoginPolicy;
use InsTickets\Services\UserService;
use PDO;
use PHPUnit\Framework\TestCase;

final class AdministrationTest extends TestCase
{
    private PDO $pdo;
    private int $admin;
    private int $agent;
    private int $requester;
    private int $category;
    private static ?string $hash = null;

    protected function setUp(): void
    {
        // La protección también se comprueba aquí antes de usar mantenimiento.
        if (getenv('APP_ENV') !== 'test' || getenv('DB_HOST') !== 'db-test') {
            throw new \RuntimeException('Se rechazó el acceso a una base no aislada.');
        }
        $root = new PDO('mysql:host=db-test;dbname=ins_tickets;charset=utf8mb4', 'root', Config::get('TEST_ROOT_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        foreach (['historial_tickets', 'comentarios', 'tickets', 'intentos_acceso', 'categorias', 'usuarios'] as $table) {
            $root->exec('DELETE FROM ' . $table);
        }
        $this->pdo = Database::connection();
        self::$hash ??= password_hash('ClaveLocal2026!', PASSWORD_BCRYPT, ['cost' => 12]);
        $query = $this->pdo->prepare('INSERT INTO usuarios (nombre,email,password_hash,rol) VALUES (?,?,?,?)');
        foreach (['admin' => 'administrador', 'agent' => 'agente', 'requester' => 'usuario'] as $key => $role) {
            $query->execute(['Demo ' . $key, $key . '@nexoti.test', self::$hash, $role]);
            $this->{$key} = (int) $this->pdo->lastInsertId();
        }
        $this->pdo->exec("INSERT INTO categorias (nombre) VALUES ('Hardware')");
        $this->category = (int) $this->pdo->lastInsertId();
    }

    private function reject(callable $operation): ValidationException
    {
        try {
            $operation();
            self::fail('La operación debía rechazarse.');
        } catch (ValidationException $exception) {
            $this->addToAssertionCount(1);
            return $exception;
        }
    }

    private function data(int $id, array $changes = []): array
    {
        $user = (new UserRepository($this->pdo))->find($id);
        return array_replace(['nombre' => $user->name, 'email' => $user->email, 'rol' => $user->role, 'activo' => $user->active ? '1' : '0', 'password' => ''], $changes);
    }

    private function createTicket(?int $agent = null): int
    {
        $query = $this->pdo->prepare('INSERT INTO tickets (solicitante_id,agente_id,categoria_id,titulo,descripcion,prioridad,submission_key) VALUES (?,?,?, ?,?, ?,?)');
        $query->execute([$this->requester, $agent, $this->category, 'Ticket de prueba', 'Descripción de prueba aislada', 'media', bin2hex(random_bytes(32))]);
        return (int) $this->pdo->lastInsertId();
    }

    public function testCreateDuplicateAndDeleteUnreferencedUser(): void
    {
        $service = new UserService($this->pdo);
        $input = ['nombre' => 'Nueva Cuenta', 'email' => ' NUEVA@NEXOTI.TEST ', 'rol' => 'usuario', 'activo' => '1', 'password' => '  ClaveLocal2026!  '];
        $id = $service->save($this->admin, null, $input);
        $user = (new UserRepository($this->pdo))->find($id);
        self::assertSame('nueva@nexoti.test', $user->email);
        self::assertTrue(password_verify($input['password'], $user->passwordHash()));
        self::assertSame(12, password_get_info($user->passwordHash())['options']['cost']);
        $this->reject(fn () => $service->save($this->admin, null, $input));
        self::assertSame(4, (int) $this->pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn());
        $service->delete($this->admin, $id);
        self::assertNull((new UserRepository($this->pdo))->find($id));
    }

    public function testSelfAdminChangesAreRejectedAndRolledBack(): void
    {
        $service = new UserService($this->pdo);
        $this->reject(fn () => $service->save($this->admin, $this->admin, $this->data($this->admin, ['activo' => '0'])));
        $this->reject(fn () => $service->save($this->admin, $this->admin, $this->data($this->admin, ['rol' => 'usuario'])));
        $this->reject(fn () => $service->delete($this->admin, $this->admin));
        $this->reject(fn () => $service->save($this->admin, $this->admin, $this->data($this->admin, ['password' => 'OtraClave2026!'])));
        $user = (new UserRepository($this->pdo))->find($this->admin);
        self::assertTrue($user->active);
        self::assertSame('administrador', $user->role);
        self::assertFalse($this->pdo->inTransaction());
    }

    public function testAgentPendingTicketsPreventDisableRoleLossAndDelete(): void
    {
        $this->createTicket($this->agent);
        $service = new UserService($this->pdo);
        $this->reject(fn () => $service->save($this->admin, $this->agent, $this->data($this->agent, ['activo' => '0'])));
        $this->reject(fn () => $service->save($this->admin, $this->agent, $this->data($this->agent, ['rol' => 'usuario'])));
        $this->reject(fn () => $service->delete($this->admin, $this->agent));
        self::assertTrue((new UserRepository($this->pdo))->find($this->agent)->active);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM tickets')->fetchColumn());
    }

    public function testReferencedUserCannotBeDeletedButCanBeDeactivated(): void
    {
        $this->createTicket();
        $service = new UserService($this->pdo);
        $this->reject(fn () => $service->delete($this->admin, $this->requester));
        $service->save($this->admin, $this->requester, $this->data($this->requester, ['activo' => '0']));
        self::assertFalse((new UserRepository($this->pdo))->find($this->requester)->active);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM tickets')->fetchColumn());
    }

    public function testCategoriesDuplicateReferencesAndUnreferencedDeletion(): void
    {
        $service = new CategoryService($this->pdo);
        $id = $service->save($this->admin, null, ['nombre' => 'Nueva Categoría', 'descripcion' => '', 'activa' => '1']);
        $this->reject(fn () => $service->save($this->admin, null, ['nombre' => 'NUEVA CATEGORÍA', 'descripcion' => '', 'activa' => '1']));
        $service->delete($this->admin, $id);
        $this->createTicket();
        $this->reject(fn () => $service->delete($this->admin, $this->category));
        $service->save($this->admin, $this->category, ['nombre' => 'Hardware', 'descripcion' => '', 'activa' => '0']);
        self::assertSame(0, (int) $this->pdo->query('SELECT activa FROM categorias')->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM tickets')->fetchColumn());
    }

    public function testNonAdminServiceCallIsDenied(): void
    {
        $this->expectException(HttpException::class);
        (new CategoryService($this->pdo))->save($this->requester, null, ['nombre' => 'No permitida', 'descripcion' => '', 'activa' => '1']);
    }

    public function testWrongCurrentPasswordAndSuccessfulChange(): void
    {
        $service = new AuthService($this->pdo);
        $this->reject(fn () => $service->changePassword($this->requester, 'incorrecta', 'ClaveNueva2026!', 'ClaveNueva2026!'));
        self::assertTrue(password_verify('ClaveLocal2026!', (new UserRepository($this->pdo))->find($this->requester)->passwordHash()));
        $service->changePassword($this->requester, 'ClaveLocal2026!', ' ClaveNueva2026! ', ' ClaveNueva2026! ');
        $hash = (new UserRepository($this->pdo))->find($this->requester)->passwordHash();
        self::assertTrue(password_verify(' ClaveNueva2026! ', $hash));
        self::assertFalse(password_verify('ClaveLocal2026!', $hash));
    }

    public function testLoginFailureWindowAndSuccessDoesNotEraseFailures(): void
    {
        $service = new AuthService($this->pdo);
        for ($i = 0; $i < 4; $i++) {
            $this->reject(fn () => $service->login('requester@nexoti.test', 'incorrecta', '127.0.0.1'));
        }
        self::assertSame($this->requester, $service->login('REQUESTER@NEXOTI.TEST', 'ClaveLocal2026!', '127.0.0.1')->id);
        $this->reject(fn () => $service->login('requester@nexoti.test', 'incorrecta', '127.0.0.1'));
        $this->reject(fn () => $service->login('requester@nexoti.test', 'ClaveLocal2026!', '127.0.0.1'));
        self::assertSame(5, (int) $this->pdo->query('SELECT COUNT(*) FROM intentos_acceso WHERE exitoso=0')->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM intentos_acceso WHERE exitoso=1')->fetchColumn());
        // Simula fallos antiguos exclusivamente en el servidor aislado.
        $root = new PDO('mysql:host=db-test;dbname=ins_tickets;charset=utf8mb4', 'root', Config::get('TEST_ROOT_PASSWORD'));
        $root->exec('UPDATE intentos_acceso SET created_at=UTC_TIMESTAMP(6)-INTERVAL 16 MINUTE');
        self::assertSame($this->requester, $service->login('requester@nexoti.test', 'ClaveLocal2026!', '127.0.0.1')->id);
    }

    public function testInactiveAndNonexistentUsersHaveSameGenericMessage(): void
    {
        (new UserService($this->pdo))->save($this->admin, $this->requester, $this->data($this->requester, ['activo' => '0']));
        $service = new AuthService($this->pdo);
        $inactive = $this->reject(fn () => $service->login('requester@nexoti.test', 'ClaveLocal2026!', '127.0.0.1'));
        $missing = $this->reject(fn () => $service->login('ausente@nexoti.test', 'ClaveLocal2026!', '127.0.0.1'));
        self::assertSame($inactive->errors, $missing->errors);
    }
}
