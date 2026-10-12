<?php use InsTickets\Core\View; use InsTickets\Core\Csrf; use InsTickets\Core\Dates; use InsTickets\Services\TicketValidation; ?>
<h1><?= View::escape($title) ?></h1>
<p class="text-secondary"><?= $ticket === null ? 'La solicitud se creará a tu nombre, abierta y sin agente asignado.' : View::escape(Dates::code((int) $ticket['id'])) . ' · Solo se habilitan los campos que podés modificar.' ?></p>
<div class="card border-0 shadow-sm"><div class="card-body p-4">
    <?php require dirname(__DIR__) . '/partials/errors.php'; ?>
    <form method="post" action="<?= $ticket === null ? '/tickets/crear' : '/tickets/editar' ?>">
        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
        <?php if ($ticket === null): ?><input type="hidden" name="submission_key" value="<?= View::escape($submission) ?>"><?php else: ?><input type="hidden" name="id" value="<?= (int) $ticket['id'] ?>"><input type="hidden" name="version" value="<?= (int) $ticket['version'] ?>"><?php endif; ?>
        <div class="mb-3"><label for="titulo" class="form-label">Título</label><input id="titulo" name="titulo" class="form-control" minlength="5" maxlength="150" required value="<?= View::escape($old['titulo'] ?? '') ?>" <?= !$canText ? 'disabled' : '' ?>></div>
        <div class="mb-3"><label for="descripcion" class="form-label">Descripción</label><textarea id="descripcion" name="descripcion" class="form-control" rows="6" minlength="10" maxlength="5000" required <?= !$canText ? 'disabled' : '' ?>><?= View::escape($old['descripcion'] ?? '') ?></textarea><p class="form-text">Describí la incidencia sin incluir contraseñas ni secretos. Entre 10 y 5,000 caracteres.</p></div>
        <?php if (!$canText): ?><p class="alert alert-info">Título y descripción son de consulta. Podés ajustar únicamente categoría y prioridad.</p><?php endif; ?>
        <div class="row"><div class="col-md-6 mb-3"><label for="categoria_id" class="form-label">Categoría</label><select id="categoria_id" name="categoria_id" class="form-select" required><option value="">Seleccionar categoría</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>" <?= (string) ($old['categoria_id'] ?? '') === (string) $category['id'] ? 'selected' : '' ?>><?= View::escape($category['nombre']) ?><?= !$category['activa'] ? ' (inactiva, conservada)' : '' ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6 mb-3"><label for="prioridad" class="form-label">Prioridad</label><select id="prioridad" name="prioridad" class="form-select" required><?php foreach (TicketValidation::PRIORITIES as $value => $label): ?><option value="<?= $value ?>" <?= ($old['prioridad'] ?? '') === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div></div>
        <p class="form-text">Baja: incidente menor con alternativa. Media: afectación parcial. Alta: bloqueo relevante. Sin tiempos SLA prometidos.</p>
        <?php if ($categories === []): ?><p role="alert" class="alert alert-warning">No hay categorías activas. Solicitá su habilitación a administración.</p><?php endif; ?>
        <button class="btn btn-primary" type="submit" <?= $categories === [] ? 'disabled' : '' ?>><?= $ticket === null ? 'Crear ticket' : 'Guardar cambios' ?></button>
        <a class="btn btn-outline-secondary ms-2" href="<?= $ticket === null ? '/tickets/mis' : '/tickets/detalle?id=' . (int) $ticket['id'] ?>">Cancelar</a>
    </form>
</div></div>
