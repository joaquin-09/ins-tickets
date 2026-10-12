<?php use InsTickets\Core\View; use InsTickets\Core\Csrf; ?>
<h1><?= View::escape($title) ?></h1>
<div class="card border-0 shadow-sm form-card"><div class="card-body p-4">
    <?php require dirname(__DIR__) . '/partials/errors.php'; ?>
    <p>Registro seleccionado: <strong><?= View::escape($name) ?></strong>.</p>
    <p>Si tiene referencias históricas, la eliminación será rechazada. Podés volver al listado y editar su condición activa.</p>
    <form method="post" action="<?= View::escape($action) ?>">
        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>"><input type="hidden" name="id" value="<?= (int) $id ?>">
        <div class="form-check mb-4"><input id="confirmar" name="confirmar" class="form-check-input" type="checkbox" value="1" required><label for="confirmar" class="form-check-label">Confirmo que deseo eliminar este registro.</label></div>
        <button class="btn btn-danger" type="submit">Confirmar eliminación</button><a class="btn btn-outline-secondary ms-2" href="<?= View::escape($back) ?>">Cancelar</a>
    </form>
</div></div>
