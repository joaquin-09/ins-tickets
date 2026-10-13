<?php

declare(strict_types=1);

namespace InsTickets\Tests\Integration;

use InsTickets\Core\Config;
use InsTickets\Core\Database;
use InsTickets\Services\TicketService;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

final class IntegrityTest extends TestCase
{
    private PDO $pdo;
    private int $owner;
    private int $category;
    private int $ticket;

    protected function setUp(): void
    {
        if (getenv('APP_ENV') !== 'test' || getenv('DB_HOST') !== 'db-test') { throw new \RuntimeException('Solo db-test.'); }
        $root = new PDO('mysql:host=db-test;dbname=ins_tickets;charset=utf8mb4', 'root', Config::get('TEST_ROOT_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        foreach (['historial_tickets', 'comentarios', 'tickets', 'intentos_acceso', 'categorias', 'usuarios'] as $table) { $root->exec('DELETE FROM ' . $table); }
        $this->pdo = Database::connection();
        static $hash = null;
        $hash ??= password_hash('ClavePruebas2026!', PASSWORD_BCRYPT, ['cost' => 12]);
        $query = $this->pdo->prepare("INSERT INTO usuarios (nombre,email,password_hash,rol) VALUES ('Solicitante QA','qa@nexoti.test',?,'usuario')");
        $query->execute([$hash]); $this->owner = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO categorias (nombre) VALUES ('Hardware')"); $this->category = (int) $this->pdo->lastInsertId();
        $this->ticket = (new TicketService($this->pdo))->create($this->owner, ['titulo' => 'Fixture de integridad', 'descripcion' => 'Descripción válida de prueba aislada', 'categoria_id' => (string) $this->category, 'prioridad' => 'media'], bin2hex(random_bytes(32)));
    }

    private function denied(string $sql, int $mysqlCode): void
    {
        try { $this->pdo->exec($sql); self::fail('La BD debía rechazar.'); }
        catch (PDOException $exception) { self::assertSame($mysqlCode, (int) $exception->errorInfo[1]); }
    }

    public function testApplicationAccountCannotDeleteTicketsAlterSchemaOrModifyHistoryAndComments(): void
    {
        self::assertStringStartsWith('ins_app@', (string) $this->pdo->query('SELECT CURRENT_USER()')->fetchColumn());
        $this->denied('DELETE FROM tickets WHERE id=' . $this->ticket, 1142);
        $this->denied('ALTER TABLE tickets ADD unauthorized_column INT', 1142);
        $this->denied("UPDATE historial_tickets SET tipo='edicion' WHERE ticket_id=" . $this->ticket, 1142);
        $this->denied('DELETE FROM historial_tickets WHERE ticket_id=' . $this->ticket, 1142);
        $this->denied("UPDATE comentarios SET contenido='No permitido'", 1142);
        $this->denied('DELETE FROM comentarios', 1142);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM tickets')->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM historial_tickets')->fetchColumn());
    }

    public function testForeignKeysAndStateChecksRejectInvalidWrites(): void
    {
        $this->denied('DELETE FROM categorias WHERE id=' . $this->category, 1451);
        $this->denied('DELETE FROM usuarios WHERE id=' . $this->owner, 1451);
        $this->denied('UPDATE tickets SET agente_id=' . $this->owner . ' WHERE id=' . $this->ticket, 3819);
        $this->denied("UPDATE tickets SET estado='resuelto' WHERE id=" . $this->ticket, 3819);
        $this->denied("UPDATE tickets SET first_response_at=created_at-INTERVAL 1 SECOND WHERE id=" . $this->ticket, 3819);
        self::assertSame('abierto', $this->pdo->query('SELECT estado FROM tickets')->fetchColumn());
    }
}
