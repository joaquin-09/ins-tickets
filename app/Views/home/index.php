<?php use InsTickets\Core\View; ?>
<span class="eyebrow">NEXOTI SOLUTIONS</span>
<h1 class="mb-2">Bienvenido, <?= View::escape($user->name) ?></h1>
<p class="text-secondary mb-2">Acceso identificado como <?= View::escape($user->role) ?>.</p>
<p class="mb-4"><?= View::escape(match ($user->role) {
    'administrador' => 'Resumen de todos los tickets del sistema.',
    'agente' => 'Resumen de tus solicitudes propias y de los tickets actualmente asignados a vos.',
    default => 'Resumen de tus solicitudes propias.',
}) ?></p>
<?php require __DIR__ . '/../reports/counts.php'; ?>
<?php if ($counts['total'] === 0): ?><p class="alert alert-info">Todavía no hay tickets en tu conjunto. Podés crear una solicitud.</p><?php endif; ?>
<section class="card border-0 shadow-sm"><div class="card-body p-4">
    <h2 class="h5">Solicitudes de soporte</h2>
    <p>Creá una solicitud y consultá su detalle y seguimiento.</p>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-primary" href="/tickets/crear">Crear ticket</a><a class="btn btn-outline-primary" href="/tickets/mis">Mis solicitudes</a>
        <?php if ($user->role === 'agente'): ?><a class="btn btn-outline-primary" href="/tickets/asignados">Asignados a mí</a><?php endif; ?>
        <?php if ($user->role === 'administrador'): ?>
            <a class="btn btn-outline-primary" href="/tickets">Gestión de tickets</a><a class="btn btn-outline-primary" href="/reportes">Reportes</a>
        <?php endif; ?>
    </div>
</div></section>
