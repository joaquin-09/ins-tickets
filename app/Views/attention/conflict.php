<?php use InsTickets\Core\View; ?>
<h1>La acción requiere revisión</h1><p role="alert" class="alert alert-warning"><?= View::escape($message) ?></p>
<p>No se guardó esta acción. Los valores enviados se conservan aquí para compararlos con el detalle actual.</p>
<dl><?php foreach ($old as $key => $value): ?><?php if ($value !== ''): ?><dt><?= View::escape($key) ?></dt><dd class="ticket-text"><?= View::escape($value) ?></dd><?php endif; ?><?php endforeach; ?></dl>
<a class="btn btn-primary" href="/tickets/detalle?id=<?= (int) $id ?>">Recargar detalle y revisar</a>
