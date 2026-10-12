<?php use InsTickets\Core\View; ?>
<div class="page-heading"><div><span class="eyebrow">ADMINISTRACIÓN</span><h1>Categorías</h1><p class="text-secondary mb-0">Clasificación de solicitudes y conservación histórica.</p></div><a href="/categorias/formulario" class="btn btn-primary">Crear categoría</a></div>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table align-middle mb-0">
    <caption class="visually-hidden">Categorías registradas y acciones disponibles</caption>
    <thead><tr><th scope="col">Nombre</th><th scope="col">Descripción</th><th scope="col">Condición</th><th scope="col">Acciones</th></tr></thead><tbody>
    <?php foreach ($list['rows'] as $row): ?>
        <tr><td><?= View::escape($row['nombre']) ?></td><td><?= View::escape($row['descripcion']) ?></td><td><span class="badge <?= $row['activa'] ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $row['activa'] ? 'Activa' : 'Inactiva' ?></span></td><td class="actions"><a class="btn btn-sm btn-outline-primary" href="/categorias/formulario?id=<?= (int) $row['id'] ?>" aria-label="Editar <?= View::escape($row['nombre']) ?>">Editar</a> <a class="btn btn-sm btn-outline-danger" href="/categorias/confirmar-eliminacion?id=<?= (int) $row['id'] ?>" aria-label="Eliminar <?= View::escape($row['nombre']) ?>">Eliminar</a></td></tr>
    <?php endforeach; ?>
    <?php if ($list['rows'] === []): ?><tr><td colspan="4" class="p-4 text-secondary">No hay categorías disponibles.</td></tr><?php endif; ?>
    </tbody></table></div></div>
<?php $basePath = '/categorias'; require dirname(__DIR__) . '/partials/pagination.php'; ?>
