<?php use InsTickets\Core\View; use InsTickets\Core\Csrf; ?>
<h1><?= View::escape($title) ?></h1><p class="text-secondary">Desactivar conserva los tickets históricos. El borrado solo se permite sin referencias.</p>
<div class="card border-0 shadow-sm form-card"><div class="card-body p-4">
    <?php require dirname(__DIR__) . '/partials/errors.php'; ?>
    <form method="post" action="/categorias/salvar">
        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>"><input type="hidden" name="id" value="<?= $id ?? '' ?>">
        <div class="mb-3"><label for="nombre" class="form-label">Nombre</label><input id="nombre" name="nombre" class="form-control" required minlength="2" maxlength="80" value="<?= View::escape($old['nombre'] ?? '') ?>"></div>
        <div class="mb-3"><label for="descripcion" class="form-label">Descripción (opcional)</label><textarea id="descripcion" name="descripcion" class="form-control" rows="3" maxlength="255"><?= View::escape($old['descripcion'] ?? '') ?></textarea></div>
        <div class="form-check mb-4"><input type="hidden" name="activa" value="0"><input id="activa" name="activa" type="checkbox" class="form-check-input" value="1" <?= (string) ($old['activa'] ?? '') === '1' ? 'checked' : '' ?>><label for="activa" class="form-check-label">Categoría activa</label></div>
        <button type="submit" class="btn btn-primary">Guardar categoría</button><a href="/categorias" class="btn btn-outline-secondary ms-2">Cancelar</a>
    </form>
</div></div>
