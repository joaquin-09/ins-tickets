<?php use InsTickets\Core\Auth; use InsTickets\Core\View; $user = Auth::requireUser(); ?>
<span class="eyebrow">NEXOTI SOLUTIONS</span>
<h1 class="mb-2">Bienvenido, <?= View::escape($user->name) ?></h1>
<p class="text-secondary mb-4">Acceso identificado como <?= View::escape($user->role) ?>.</p>
<section class="card border-0 shadow-sm"><div class="card-body p-4">
    <h2 class="h5">Acceso y administración disponibles</h2>
    <p>Podés cambiar tu contraseña y cerrar tu sesión de forma segura.</p>
    <?php if ($user->role === 'administrador'): ?>
        <p>La administración de usuarios y categorías está disponible en el menú.</p>
        <a class="btn btn-primary me-2" href="/usuarios">Gestionar usuarios</a>
        <a class="btn btn-outline-primary" href="/categorias">Gestionar categorías</a>
    <?php endif; ?>
</div></section>
<p class="mt-4 text-secondary">Incremento 2: la gestión de tickets, atención y paneles/reportes se incorporarán en los siguientes módulos.</p>
