<?php use InsTickets\Core\View; ?>
<div class="d-flex flex-wrap gap-3 align-items-center justify-content-between mt-3">
    <span class="text-secondary"><?= (int) $list['total'] ?> registros · Página <?= (int) $list['page'] ?> de <?= (int) $list['pages'] ?></span>
    <nav aria-label="Paginación" class="d-flex gap-2">
        <?php if ($list['page'] > 1): ?><a class="btn btn-outline-primary" href="<?= View::escape($basePath) ?>?pagina=<?= $list['page'] - 1 ?>">Anterior</a><?php endif; ?>
        <?php if ($list['page'] < $list['pages']): ?><a class="btn btn-outline-primary" href="<?= View::escape($basePath) ?>?pagina=<?= $list['page'] + 1 ?>">Siguiente</a><?php endif; ?>
    </nav>
</div>
