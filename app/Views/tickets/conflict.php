<?php use InsTickets\Core\View; ?>
<h1>El ticket cambió</h1><p role="alert" class="alert alert-warning"><?= View::escape($message) ?></p>
<p>Conservá estos valores enviados y comparalos con la versión actual antes de guardar nuevamente.</p>
<dl><?php foreach ($old as $field => $value): ?><dt><?= View::escape($field) ?></dt><dd class="ticket-text"><?= View::escape($value) ?></dd><?php endforeach; ?></dl>
<a class="btn btn-primary" href="/tickets/editar?id=<?= (int) $id ?>">Recargar formulario actual</a><a class="btn btn-outline-secondary ms-2" href="/tickets/detalle?id=<?= (int) $id ?>">Consultar ticket</a>
