<?php

declare(strict_types=1);

use InsTickets\Core\Database;
use InsTickets\Verification\Verification;

require dirname(__DIR__) . '/bootstrap/app.php';
require __DIR__ . '/support/Verification.php';

try {
    Verification::isolated();
    $pdo = Database::connection();
    foreach (Verification::TABLES as $table) {
        if ((int) $pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn() !== 0) {
            throw new RuntimeException('La base de validación no está vacía. No se borraron registros.');
        }
    }
    $pdo->beginTransaction();
    $users = [];
    $query = $pdo->prepare('INSERT INTO usuarios (nombre,email,password_hash,rol) VALUES (?,?,?,?)');
    $hash = password_hash('ValidacionLocal2026!', PASSWORD_BCRYPT, ['cost' => 12]);
    foreach (['admin' => 'administrador', 'agente1' => 'agente', 'agente2' => 'agente', 'usuario1' => 'usuario', 'usuario2' => 'usuario'] as $key => $role) {
        $query->execute(['QA ' . $key, $key . '@validation.test', $hash, $role]);
        $users[$key] = (int) $pdo->lastInsertId();
    }
    $categories = [];
    foreach (['Hardware', 'Software', 'Red', 'Acceso', 'Otros'] as $name) {
        $query = $pdo->prepare('INSERT INTO categorias (nombre) VALUES (?)');
        $query->execute([$name]);
        $categories[] = (int) $pdo->lastInsertId();
    }
    $base = new DateTimeImmutable('-10 days', new DateTimeZone('UTC'));
    $insert = $pdo->prepare('INSERT INTO tickets (solicitante_id,agente_id,categoria_id,titulo,descripcion,prioridad,estado,first_response_at,resolved_at,resolved_by,resolution_summary,closed_at,closed_by,submission_key,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $history = $pdo->prepare('INSERT INTO historial_tickets (ticket_id,actor_id,tipo,anterior,nuevo,created_at) VALUES (?,?,?,?,?,?)');
    $comment = $pdo->prepare('INSERT INTO comentarios (ticket_id,autor_id,autor_rol,contenido,created_at) VALUES (?,?,?,?,?)');
    for ($i = 0; $i < 500; $i++) {
        $state = ['abierto', 'en_progreso', 'resuelto', 'cerrado'][$i % 4];
        $created = $base->modify('+' . $i . ' minutes');
        $time = static fn (int $hours): string => $created->modify('+' . $hours . ' hours')->format('Y-m-d H:i:s.u');
        $owner = $users[$i % 2 === 0 ? 'usuario1' : 'usuario2'];
        $agent = $state === 'abierto' ? null : $users[$i % 3 === 0 ? 'agente2' : 'agente1'];
        $resolved = in_array($state, ['resuelto', 'cerrado'], true);
        $resolver = $resolved ? ($i % 5 === 0 ? $users['admin'] : $agent) : null;
        $first = $state !== 'abierto' && $i % 7 !== 0 ? $time(1) : null;
        $summary = $resolved ? 'Corrección sintética preparada para validación.' : null;
        $insert->execute([$owner, $agent, $categories[$i % 5], 'Fixture de rendimiento ' . ($i + 1),
            'Descripción sintética para medir la aplicación local con quinientos tickets.', ['baja', 'media', 'alta'][$i % 3], $state,
            $first, $resolved ? $time(4) : null, $resolver, $summary, $state === 'cerrado' ? $time(5) : null,
            $state === 'cerrado' ? $owner : null, bin2hex(random_bytes(32)), $time(0), $time($state === 'cerrado' ? 5 : ($resolved ? 4 : ($first === null ? 0 : 1)))]);
        $id = (int) $pdo->lastInsertId();
        $event = static function (int $actor, string $type, ?array $old, array $new, int $hours) use ($history, $id, $time): void {
            $history->execute([$id, $actor, $type, $old === null ? null : json_encode($old, JSON_THROW_ON_ERROR), json_encode($new, JSON_THROW_ON_ERROR), $time($hours)]);
        };
        $event($owner, 'creacion', null, ['estado' => 'abierto'], 0);
        if ($agent !== null) {
            $event($users['admin'], 'asignacion', ['agente_id' => null], ['agente_id' => $agent], 0);
            $event($agent, 'estado', ['estado' => 'abierto'], ['estado' => 'en_progreso'], 0);
        }
        if ($first !== null) { $comment->execute([$id, $agent, 'agente', 'Comentario sintético de soporte para validación.', $first]); }
        if ($resolved) { $event($resolver, 'estado', ['estado' => 'en_progreso'], ['estado' => 'resuelto', 'resolved_by' => $resolver, 'resolution_summary' => $summary], 4); }
        if ($state === 'cerrado') { $event($owner, 'estado', ['estado' => 'resuelto'], ['estado' => 'cerrado', 'closed_by' => $owner], 5); }
    }
    $pdo->commit();
    echo "Fixture aislado: 500 tickets, 125 por estado, cinco usuarios y cinco categorías. No son datos de atención real.\n";
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    fwrite(STDERR, "Fixture rechazado o fallido; no se confirmó una carga parcial.\n");
    exit(1);
}
