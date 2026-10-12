<?php use InsTickets\Core\View; use InsTickets\Core\Auth; ?>
<div class="page-heading"><div><span class="eyebrow">ADMINISTRACIÓN</span><h1>Usuarios</h1><p class="text-secondary mb-0">Cuentas, roles y acceso al sistema.</p></div><a href="/usuarios/formulario" class="btn btn-primary">Crear usuario</a></div>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table align-middle mb-0">
    <caption class="visually-hidden">Usuarios registrados y sus acciones de administración</caption>
    <thead><tr><th scope="col">Nombre</th><th scope="col">Correo</th><th scope="col">Rol</th><th scope="col">Condición</th><th scope="col">Acciones</th></tr></thead><tbody>
    <?php foreach ($list['rows'] as $row): ?>
        <tr><td><?= View::escape($row['nombre']) ?></td><td><?= View::escape($row['email']) ?></td><td><?= View::escape($row['rol']) ?></td><td><span class="badge <?= $row['activo'] ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $row['activo'] ? 'Activo' : 'Inactivo' ?></span></td><td class="actions"><a class="btn btn-sm btn-outline-primary" href="/usuarios/formulario?id=<?= (int) $row['id'] ?>" aria-label="Editar <?= View::escape($row['nombre']) ?>">Editar</a><?php if ((int) $row['id'] !== Auth::user()->id): ?> <a class="btn btn-sm btn-outline-danger" href="/usuarios/confirmar-eliminacion?id=<?= (int) $row['id'] ?>" aria-label="Eliminar <?= View::escape($row['nombre']) ?>">Eliminar</a><?php endif; ?></td></tr>
    <?php endforeach; ?>
    <?php if ($list['rows'] === []): ?><tr><td colspan="5" class="p-4 text-secondary">No hay usuarios disponibles.</td></tr><?php endif; ?>
    </tbody></table></div></div>
<?php $basePath = '/usuarios'; require dirname(__DIR__) . '/partials/pagination.php'; ?>
