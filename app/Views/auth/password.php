<?php use InsTickets\Core\View; use InsTickets\Core\Csrf; ?>
<h1>Cambiar mi contraseña</h1><p class="text-secondary">Verificá tu contraseña actual para establecer una nueva.</p>
<div class="card border-0 shadow-sm form-card"><div class="card-body p-4">
    <?php require dirname(__DIR__) . '/partials/errors.php'; ?>
    <form method="post" action="/cuenta/password">
        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
        <div class="mb-3"><label for="actual" class="form-label">Contraseña actual</label><input id="actual" name="actual" type="password" class="form-control" required autocomplete="current-password" maxlength="64"></div>
        <div class="mb-3"><label for="password" class="form-label">Nueva contraseña</label><input id="password" name="password" type="password" class="form-control" required minlength="10" maxlength="64" autocomplete="new-password" aria-describedby="password-help"><p id="password-help" class="form-text">Entre 10 y 64 caracteres UTF-8 y máximo 72 bytes. Los espacios forman parte de la contraseña.</p></div>
        <div class="mb-4"><label for="confirmacion" class="form-label">Confirmar nueva contraseña</label><input id="confirmacion" name="confirmacion" type="password" class="form-control" required minlength="10" maxlength="64" autocomplete="new-password"></div>
        <button class="btn btn-primary" type="submit">Actualizar contraseña</button><a class="btn btn-outline-secondary ms-2" href="/">Cancelar</a>
    </form>
</div></div>
