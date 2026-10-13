<?php

declare(strict_types=1);

use InsTickets\Core\Database;
use InsTickets\Verification\HttpClient;
use InsTickets\Verification\Verification;

require dirname(__DIR__) . '/bootstrap/app.php';
require __DIR__ . '/support/Verification.php';
require __DIR__ . '/support/HttpClient.php';

$checks = [];
$id = null;
$passed = false;
try {
    Verification::isolated();
    $pdo = Database::connection();
    $root = Verification::maintenance();
    $expect = static function (bool $condition, string $name) use (&$checks): void {
        $checks[$name] = $condition;
        if (!$condition) { throw new RuntimeException('Comprobación HTTP fallida: ' . $name); }
        echo $name . ": OK\n";
    };
    $new = new HttpClient();
    $form = $new->request('/login');
    $anonymousSession = $new->sessionId();
    $headers = implode("\n", $form['headers']);
    $expect($form['status'] === 200 && stripos($headers, 'HttpOnly') !== false && stripos($headers, 'SameSite=Lax') !== false, 'Cookie HttpOnly/SameSite');
    $expect(stripos($headers, 'Content-Security-Policy:') !== false && stripos($headers, 'X-Content-Type-Options: nosniff') !== false && stripos($headers, 'Cache-Control: no-store') !== false, 'Cabeceras defensivas');
    foreach (['/login', '/logout', '/tickets/crear', '/tickets/asignar', '/tickets/comentar', '/tickets/transicion'] as $path) {
        $expect($new->request($path, [])['status'] === 403, 'CSRF ausente ' . $path);
    }
    foreach (['/.env', '/composer.json', '/database/schema.sql', '/storage/logs/php-error.log'] as $path) {
        $expect($new->request($path)['status'] === 404, 'Archivo privado ' . $path);
    }
    $new->login('usuario1@validation.test');
    $expect($new->sessionId() !== null && $new->sessionId() !== $anonymousSession, 'Regeneración al login');
    $expect($new->request('/reportes')['status'] === 403 && $new->request('/usuarios')['status'] === 403, 'Autorización administrativa');
    $expect($new->request('/tickets/asignados')['status'] === 403, 'Lista exclusiva agente');
    $foreign = (int) $pdo->query("SELECT t.id FROM tickets t JOIN usuarios u ON u.id=t.solicitante_id WHERE u.email='usuario2@validation.test' ORDER BY t.id LIMIT 1")->fetchColumn();
    $expect($foreign > 0 && $new->request('/tickets/detalle?id=' . $foreign)['status'] === 404, 'Ticket ajeno por URL');
    $form = $new->request('/tickets/crear');
    $csrf = HttpClient::token($form['body']);
    $submission = HttpClient::token($form['body'], 'submission_key');
    $category = (int) $pdo->query('SELECT MIN(id) FROM categorias')->fetchColumn();
    $data = ['_csrf' => $csrf, 'submission_key' => $submission, 'categoria_id' => (string) $category, 'prioridad' => 'media',
        'titulo' => 'Prueba HTTP de aceptación', 'descripcion' => '<script>alert("INS-XSS")</script> Texto sintético de validación.'];
    $expect($new->request('/tickets/crear', array_replace($data, ['titulo' => '']))['status'] === 422, 'Creación inválida');
    $created = $new->request('/tickets/crear', $data);
    foreach ($created['headers'] as $header) {
        if (preg_match('/^Location:\s*\/tickets\/detalle\?id=([0-9]+)/i', $header, $match)) { $id = (int) $match[1]; }
    }
    $expect($created['status'] === 303 && $id !== null, 'Creación válida PRG');
    $again = $new->request('/tickets/crear', $data);
    $query = $pdo->prepare('SELECT COUNT(*) FROM tickets WHERE submission_key=?');
    $query->execute([$submission]);
    $expect($again['status'] === 303 && (int) $query->fetchColumn() === 1, 'Doble envío sin duplicado');
    $detail = $new->request('/tickets/detalle?id=' . $id);
    $expect($detail['status'] === 200 && !str_contains($detail['body'], '<script>alert("INS-XSS")</script>') && str_contains($detail['body'], '&lt;script&gt;'), 'XSS almacenado escapado');
    $post = ['_csrf' => HttpClient::token($detail['body']), 'id' => (string) $id, 'version' => '1', 'contenido' => 'Comentario propio de prueba HTTP.'];
    $expect($new->request('/tickets/comentar', $post)['status'] === 303, 'Comentario propio válido');
    $expect($new->request('/tickets/comentar', $post)['status'] === 409, 'Versión vieja rechazada');
    $query = $pdo->prepare('SELECT first_response_at FROM tickets WHERE id=?'); $query->execute([$id]);
    $expect($query->fetchColumn() === null, 'Comentario propio no inicia primera respuesta');
    $admin = new HttpClient(); $admin->login('admin@validation.test');
    $agentId = (int) $pdo->query("SELECT id FROM usuarios WHERE email='agente1@validation.test'")->fetchColumn();
    $adminDetail = $admin->request('/tickets/detalle?id=' . $id);
    $expect($admin->request('/tickets/asignar', ['_csrf' => HttpClient::token($adminDetail['body']), 'id' => (string) $id, 'version' => '2', 'agente_id' => (string) $agentId])['status'] === 303, 'Asignación administrativa');
    $agent = new HttpClient(); $agent->login('agente1@validation.test');
    $agentDetail = $agent->request('/tickets/detalle?id=' . $id);
    $csrfAgent = HttpClient::token($agentDetail['body']);
    $expect($agent->request('/tickets/transicion', ['_csrf' => $csrfAgent, 'id' => (string) $id, 'version' => '3', 'estado' => 'en_progreso'])['status'] === 303, 'Inicio atención');
    $expect($agent->request('/tickets/comentar', ['_csrf' => $csrfAgent, 'id' => (string) $id, 'version' => '4', 'contenido' => 'Primera intervención sintética del soporte.'])['status'] === 303, 'Primera respuesta soporte');
    $expect($agent->request('/tickets/transicion', ['_csrf' => $csrfAgent, 'id' => (string) $id, 'version' => '5', 'estado' => 'resuelto', 'resolution_summary' => 'Resolución sintética verificada por flujo HTTP.'])['status'] === 303, 'Resolución autorizada');
    $detail = $new->request('/tickets/detalle?id=' . $id);
    $expect($new->request('/tickets/transicion', ['_csrf' => HttpClient::token($detail['body']), 'id' => (string) $id, 'version' => '6', 'estado' => 'cerrado'])['status'] === 303, 'Cierre por solicitante');
    $expect($new->request('/tickets/comentar', ['_csrf' => HttpClient::token($detail['body']), 'id' => (string) $id, 'version' => '7', 'contenido' => 'No debe aceptarse este comentario.'])['status'] === 422, 'Cerrado no admite comentario');
    $expect($admin->request('/reportes?desde=no-es-fecha')['status'] === 422, 'Filtros de reporte inválidos');
    $report = $admin->request('/reportes?desde=2099-01-01');
    $expect($report['status'] === 200 && str_contains($report['body'], 'Sin datos'), 'Reporte vacío comprensible');
    // Rol/activo cambian en el servidor aislado; la siguiente petición debe refrescarlos.
    $root->exec('UPDATE usuarios SET activo=0 WHERE id=' . $agentId);
    try { $expect($agent->request('/tickets/asignados')['status'] === 303, 'Sesión de cuenta desactivada rechazada'); }
    finally { $root->exec('UPDATE usuarios SET activo=1 WHERE id=' . $agentId); }
    $page = $new->request('/');
    $expect($new->request('/logout', ['_csrf' => HttpClient::token($page['body'])])['status'] === 303, 'Logout POST');
    $expect($new->request('/tickets/mis')['status'] === 303, 'Sesión destruida');
    $passed = true;
} catch (Throwable $exception) {
    fwrite(STDERR, "Verificación HTTP incompleta; revisar la última comprobación y logs privados.\n");
} finally {
    // Retira únicamente el ticket creado por esta prueba, solo en db-validation.
    // No se ejecuta contra el servidor de exposición; no reinicia los 500 fixtures.
    if (isset($root) && $id !== null) {
        try {
            $root->beginTransaction();
            foreach (['comentarios', 'historial_tickets', 'tickets'] as $table) {
                $query = $root->prepare('DELETE FROM ' . $table . ' WHERE ' . ($table === 'tickets' ? 'id' : 'ticket_id') . '=?');
                $query->execute([$id]);
            }
            $root->commit();
        } catch (Throwable) {
            if ($root->inTransaction()) { $root->rollBack(); }
            $passed = false;
            fwrite(STDERR, "Falló limpieza del fixture HTTP aislado.\n");
        }
    }
    Verification::evidence('http-security', ['passed' => $passed, 'checks' => $checks,
        'scope' => 'HTTP real contra Apache y MySQL temporales; no sustituye revisión visual/teclado, instalación ni aceptación humana.']);
}
exit($passed ? 0 : 1);
