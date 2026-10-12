<?php use InsTickets\Core\View; ?>
<?php if (($errors ?? []) !== []): ?>
<div class="alert alert-danger" role="alert" tabindex="-1">
    <strong>No se guardaron los cambios.</strong><ul class="mb-0 mt-2">
    <?php foreach ($errors as $error): ?><li><?= View::escape($error) ?></li><?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>
