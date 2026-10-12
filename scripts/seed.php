<?php

declare(strict_types=1);

use InsTickets\Core\Config;
use InsTickets\Core\Database;

require dirname(__DIR__) . '/bootstrap/app.php';

if (Config::get('APP_ENV') !== 'local') {
    fwrite(STDERR, "La semilla es exclusiva del entorno local ficticio.\n");
    exit(1);
}

$pdo = null;
$lock = false;
try {
    $pdo = Database::connection();
    $lock = (int) $pdo->query("SELECT GET_LOCK('ins_tickets.seed', 5)")->fetchColumn() === 1;
    if (!$lock) {
        throw new RuntimeException('Otra inicializacion esta en curso.');
    }
    $pdo->beginTransaction();
    foreach (['usuarios', 'categorias', 'tickets', 'comentarios', 'historial_tickets', 'intentos_acceso'] as $table) {
        if ((int) $pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn() !== 0) {
            throw new RuntimeException('La base no está vacía. La semilla no se repite ni reemplaza datos.');
        }
    }
    $base = new DateTimeImmutable('-48 hours', new DateTimeZone('UTC'));
    $time = static fn (int $hours): string => $base->modify('+' . $hours . ' hours')->format('Y-m-d H:i:s.u');
    $insertUser = $pdo->prepare('INSERT INTO usuarios (nombre,email,password_hash,rol,created_at,updated_at) VALUES (?,?,?,?,?,?)');
    $users = [];
    foreach ([
        ['admin', 'Administración Demo', 'administrador'],
        ['agente1', 'Agente Uno Demo', 'agente'],
        ['agente2', 'Agente Dos Demo', 'agente'],
        ['usuario1', 'Solicitante Uno Demo', 'usuario'],
        ['usuario2', 'Solicitante Dos Demo', 'usuario'],
    ] as [$key, $name, $role]) {
        $insertUser->execute([$name, $key . '@nexoti.test', password_hash('DemoLocal2026!', PASSWORD_BCRYPT, ['cost' => 12]), $role, $time(0), $time(0)]);
        $users[$key] = (int) $pdo->lastInsertId();
    }
    $insertCategory = $pdo->prepare('INSERT INTO categorias (nombre,descripcion,created_at,updated_at) VALUES (?,?,?,?)');
    $categories = [];
    foreach (['Hardware', 'Software', 'Red', 'Acceso y credenciales', 'Otros'] as $name) {
        $insertCategory->execute([$name, 'Categoría ficticia de demostración', $time(0), $time(0)]);
        $categories[$name] = (int) $pdo->lastInsertId();
    }
    $insertTicket = $pdo->prepare('INSERT INTO tickets (solicitante_id,categoria_id,titulo,descripcion,prioridad,submission_key,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?)');
    $insertEvent = $pdo->prepare('INSERT INTO historial_tickets (ticket_id,actor_id,tipo,anterior,nuevo,created_at) VALUES (?,?,?,?,?,?)');
    $event = static function (int $ticket, int $actor, string $type, ?array $old, array $new, int $hours) use ($insertEvent, $time): void {
        $insertEvent->execute([$ticket, $actor, $type, $old === null ? null : json_encode($old, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), json_encode($new, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $time($hours)]);
    };
    foreach ([
        ['usuario1', 'agente1', 'Hardware', 'Equipo no enciende', 'alta', 'resuelto', 1, 4, 'agente1'],
        ['usuario2', 'agente2', 'Software', 'Aplicación no abre', 'media', 'cerrado', 3, 8, 'admin'],
        ['usuario1', null, 'Red', 'Conexión intermitente', 'baja', 'abierto', null, null, null],
        ['usuario2', 'agente2', 'Acceso y credenciales', 'Acceso a cuenta bloqueado', 'alta', 'en_progreso', null, null, null],
    ] as [$requester, $agent, $category, $title, $priority, $state, $responseHour, $resolveHour, $resolver]) {
        $insertTicket->execute([$users[$requester], $categories[$category], $title, 'Incidencia ficticia preparada para la demostración académica de INS Tickets.', $priority, bin2hex(random_bytes(32)), $time(0), $time(0)]);
        $id = (int) $pdo->lastInsertId();
        $event($id, $users[$requester], 'creacion', null, ['estado' => 'abierto', 'titulo' => $title, 'categoria_id' => $categories[$category], 'prioridad' => $priority], 0);
        if ($agent !== null) {
            $pdo->prepare('UPDATE tickets SET agente_id=?,version=version+1,updated_at=? WHERE id=?')->execute([$users[$agent], $time(0), $id]);
            $event($id, $users['admin'], 'asignacion', ['agente_id' => null], ['agente_id' => $users[$agent]], 0);
            $pdo->prepare("UPDATE tickets SET estado='en_progreso',version=version+1,updated_at=? WHERE id=?")->execute([$time(0), $id]);
            $event($id, $users[$agent], 'estado', ['estado' => 'abierto'], ['estado' => 'en_progreso'], 0);
        }
        if ($responseHour !== null) {
            $pdo->prepare("INSERT INTO comentarios (ticket_id,autor_id,autor_rol,contenido,created_at) VALUES (?,?,'agente',?,?)")->execute([$id, $users[$agent], 'Se inició la revisión de esta incidencia ficticia.', $time($responseHour)]);
            $pdo->prepare('UPDATE tickets SET first_response_at=?,version=version+1,updated_at=? WHERE id=?')->execute([$time($responseHour), $time($responseHour), $id]);
        }
        if ($resolveHour !== null) {
            $summary = 'Se aplicó la corrección ficticia y se comprobó el funcionamiento en los datos de demostración.';
            $pdo->prepare("UPDATE tickets SET estado='resuelto',resolved_at=?,resolved_by=?,resolution_summary=?,version=version+1,updated_at=? WHERE id=?")->execute([$time($resolveHour), $users[$resolver], $summary, $time($resolveHour), $id]);
            $event($id, $users[$resolver], 'estado', ['estado' => 'en_progreso'], ['estado' => 'resuelto', 'resolution_summary' => $summary, 'resolved_by' => $users[$resolver]], $resolveHour);
        }
        if ($state === 'cerrado') {
            $pdo->prepare("UPDATE tickets SET estado='cerrado',closed_at=?,closed_by=?,version=version+1,updated_at=? WHERE id=?")->execute([$time(9), $users[$requester], $time(9), $id]);
            $event($id, $users[$requester], 'estado', ['estado' => 'resuelto'], ['estado' => 'cerrado', 'closed_by' => $users[$requester]], 9);
        }
    }
    $pdo->commit();
    echo "Semilla aplicada: 5 usuarios, 5 categorías, 4 tickets, 2 comentarios y 13 eventos.\n";
    echo "Son datos ficticios; no son resultados de pruebas ni atención real.\n";
} catch (Throwable $exception) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Mensaje conocido sin SQL, credenciales ni traza.
    $message = $exception instanceof RuntimeException && !$exception instanceof PDOException
        ? $exception->getMessage() : 'Fallo al inicializar. Revisá los servicios y el esquema. No se confirmaron cambios parciales.';
    fwrite(STDERR, $message . "\n");
    exit(1);
} finally {
    if ($lock && $pdo instanceof PDO) {
        $pdo->query("SELECT RELEASE_LOCK('ins_tickets.seed')");
    }
}
