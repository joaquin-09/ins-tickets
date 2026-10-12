<?php use InsTickets\Core\View; use InsTickets\Core\Csrf; ?>
<div class="login-card card border-0 shadow-sm mx-auto"><div class="card-body p-4 p-md-5">
    <span class="eyebrow">NEXOTI SOLUTIONS</span><h1 class="mt-2">INS Tickets</h1>
    <p class="text-secondary">Iniciá sesión para acceder al soporte interno.</p>
    <?php require dirname(__DIR__) . '/partials/errors.php'; ?>
    <form method="post" action="/login">
        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
        <div class="mb-3"><label for="email" class="form-label">Correo electrónico</label><input id="email" name="email" type="email" class="form-control" maxlength="254" required autocomplete="username" value="<?= View::escape($old['email'] ?? '') ?>"></div>
        <div class="mb-4"><label for="password" class="form-label">Contraseña</label><input id="password" name="password" type="password" class="form-control" required autocomplete="current-password" maxlength="64"></div>
        <button class="btn btn-primary w-100" type="submit">Iniciar sesión</button>
    </form>
    <p class="small text-secondary mt-4 mb-0">Las cuentas son creadas por administración. Si necesitás acceso, contactá al personal responsable.</p>
</div></div>
