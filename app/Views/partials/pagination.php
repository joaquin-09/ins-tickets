<?php use InsTickets\Core\View; $pageKey = $paginationPageKey ?? 'pagina'; $queryParameters = $paginationParams ?? []; unset($queryParameters[$pageKey]); ?>
<div class="d-flex flex-wrap gap-3 align-items-center justify-content-between mt-3">
    <span class="text-secondary"><?= (int) $list['total'] ?> registros · Página <?= (int) $list['page'] ?> de <?= (int) $list['pages'] ?></span>
    <nav aria-label="Paginación" class="d-flex gap-2">
        <?php if ($list['page'] > 1): ?><a class="btn btn-outline-primary" href="<?= View::escape($basePath . '?' . http_build_query($queryParameters + [$pageKey => $list['page'] - 1])) ?>">Anterior</a><?php endif; ?>
        <?php if ($list['page'] < $list['pages']): ?><a class="btn btn-outline-primary" href="<?= View::escape($basePath . '?' . http_build_query($queryParameters + [$pageKey => $list['page'] + 1])) ?>">Siguiente</a><?php endif; ?>
    </nav>
</div>
