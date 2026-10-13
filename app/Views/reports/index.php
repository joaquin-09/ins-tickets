<?php
use InsTickets\Core\View;
use InsTickets\Services\TicketValidation;
?>
<span class="eyebrow">SEGUIMIENTO DEL SOPORTE</span>
<h1>Reportes</h1>
<p class="text-secondary">Todos los tickets por defecto. Los filtros afectan cada conteo y promedio de esta vista.</p>
<p>El periodo corresponde a la <strong>fecha de creación</strong> del ticket, con días completos en America/El_Salvador. Los tiempos son horas naturales transcurridas.</p>
<?php require __DIR__ . '/../partials/errors.php'; ?>
<form method="get" action="/reportes" class="card border-0 shadow-sm mb-4"><div class="card-body">
    <div class="row g-3">
        <?php foreach (['desde' => 'Creado desde', 'hasta' => 'Creado hasta'] as $key => $label): ?>
            <div class="col-md-6 col-xl-4"><label class="form-label" for="<?= $key ?>"><?= $label ?></label><input class="form-control" type="date" min="1000-01-01" max="9998-12-31" id="<?= $key ?>" name="<?= $key ?>" value="<?= View::escape($filters[$key]) ?>"></div>
        <?php endforeach; ?>
        <?php foreach (['estado' => ['Estado', TicketValidation::STATES], 'prioridad' => ['Prioridad', TicketValidation::PRIORITIES]] as $key => [$label, $options]): ?>
            <div class="col-md-6 col-xl-4"><label class="form-label" for="<?= $key ?>"><?= $label ?></label><select class="form-select" name="<?= $key ?>" id="<?= $key ?>"><option value="">Todos</option>
                <?php foreach ($options as $value => $text): ?><option value="<?= View::escape($value) ?>" <?= $filters[$key] === $value ? 'selected' : '' ?>><?= View::escape($text) ?></option><?php endforeach; ?>
            </select></div>
        <?php endforeach; ?>
        <div class="col-md-6 col-xl-4"><label class="form-label" for="categoria_id">Categoría</label><select class="form-select" name="categoria_id" id="categoria_id"><option value="">Todas</option>
            <?php if ($filters['categoria_id'] !== '' && !in_array($filters['categoria_id'], array_map('strval', array_column($categories, 'id')), true)): ?><option selected value="<?= View::escape($filters['categoria_id']) ?>">ID seleccionado: <?= View::escape($filters['categoria_id']) ?> (no disponible)</option><?php endif; ?>
            <?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>" <?= $filters['categoria_id'] === (string) $category['id'] ? 'selected' : '' ?>><?= View::escape($category['nombre']) ?><?= $category['activa'] ? '' : ' (inactiva)' ?></option><?php endforeach; ?>
        </select></div>
        <div class="col-md-6 col-xl-4"><label class="form-label" for="agente_id">Agente actualmente asignado</label><select class="form-select" name="agente_id" id="agente_id"><option value="">Todos</option><option value="sin_asignar" <?= $filters['agente_id'] === 'sin_asignar' ? 'selected' : '' ?>>Sin asignar</option>
            <?php if ($filters['agente_id'] !== '' && $filters['agente_id'] !== 'sin_asignar' && !in_array($filters['agente_id'], array_map('strval', array_column($users, 'id')), true)): ?><option selected value="<?= View::escape($filters['agente_id']) ?>">ID seleccionado: <?= View::escape($filters['agente_id']) ?> (no disponible)</option><?php endif; ?>
            <?php foreach ($users as $agent): ?>
                <option value="<?= (int) $agent['id'] ?>" <?= $filters['agente_id'] === (string) $agent['id'] ? 'selected' : '' ?>><?= View::escape($agent['nombre']) ?><?= $agent['activo'] ? '' : ' (inactivo)' ?></option>
            <?php endforeach; ?>
        </select></div>
    </div><div class="d-flex gap-2 mt-3"><button class="btn btn-primary" type="submit">Aplicar filtros</button><a class="btn btn-outline-primary" href="/reportes">Limpiar filtros</a></div>
</div></form>
<?php if ($report === null): ?>
    <p class="alert alert-info">Corregí los filtros para consultar las cifras. No se ejecutó un reporte con filtros inválidos.</p>
<?php else: $counts = $report['counts']; ?>
    <?php if ($counts['total'] === 0): ?><p class="alert alert-info">No hay tickets que coincidan con los filtros.</p><?php endif; ?>
    <?php require __DIR__ . '/counts.php'; ?>
    <div class="row g-3 mb-4">
        <?php foreach (['response' => 'Primera respuesta promedio', 'resolution' => 'Resolución promedio'] as $key => $label): $metric = $report[$key]; ?>
            <section class="col-md-6"><div class="card border-0 shadow-sm h-100"><div class="card-body"><h2 class="h5"><?= $label ?></h2>
                <p class="h2"><?= View::escape($metric['label']) ?></p><p class="text-secondary mb-0">Muestra n = <?= (int) $metric['n'] ?> tickets</p>
            </div></div></section>
        <?php endforeach; ?>
    </div>
    <p class="text-secondary">Cada promedio excluye tickets sin su fecha válida. La resolución incluye resueltos y cerrados. La primera respuesta corresponde a la primera intervención por comentario del soporte, distinta del solicitante.</p>
    <?php foreach (['categorias' => 'Tickets por categoría', 'agentes' => 'Tickets por agente actualmente asignado', 'resolutores' => 'Tickets resueltos por quien resolvió'] as $key => $label): $list = $report['groups'][$key]; ?>
        <section class="card border-0 shadow-sm mb-4" id="<?= $key ?>"><div class="card-body"><h2 class="h5"><?= $label ?></h2>
            <?php if ($key === 'resolutores'): ?><p class="text-secondary">Incluye administradores que resolvieron y tickets posteriormente cerrados. Usa el resolutor registrado al resolver.</p><?php endif; ?>
            <div class="table-responsive"><table class="table"><caption class="visually-hidden"><?= $label ?> del conjunto filtrado</caption>
                <thead><tr><th scope="col">Nombre</th><th scope="col">Tickets</th></tr></thead><tbody>
                    <?php if ($list['rows'] === []): ?><tr><td colspan="2">Sin datos para este grupo.</td></tr><?php endif; ?>
                    <?php foreach ($list['rows'] as $row): ?><tr><th scope="row"><?= View::escape($row['nombre']) ?><?= $row['id'] === null ? '' : ' · #' . (int) $row['id'] ?></th><td><?= (int) $row['cantidad'] ?></td></tr><?php endforeach; ?>
                </tbody></table></div>
            <?php
            $basePath = '/reportes';
            $paginationPageKey = $key . '_pagina';
            $paginationParams = $filters;
            foreach ($report['groups'] as $groupKey => $groupList) { $paginationParams[$groupKey . '_pagina'] = $groupList['page']; }
            require __DIR__ . '/../partials/pagination.php';
            ?>
        </div></section>
    <?php endforeach; ?>
<?php endif; ?>
