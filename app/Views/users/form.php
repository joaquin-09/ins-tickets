<?php use InsTickets\Core\View; use InsTickets\Core\Csrf; use InsTickets\Core\Auth; $self = $id === Auth::user()->id; ?>
<h1><?= View::escape($title) ?></h1><p class="text-secondary">La contraseña existente nunca se muestra.</p>
<div class="card border-0 shadow-sm form-card"><div class="card-body p-4">
    <?php require dirname(__DIR__) . '/partials/errors.php'; ?>
    <form method="post" action="/usuarios/salvar">
        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>"><input type="hidden" name="id" value="<?= $id ?? '' ?>">
        <div class="mb-3"><label for="nombre" class="form-label">Nombre</label><input id="nombre" name="nombre" class="form-control" required minlength="2" maxlength="100" value="<?= View::escape($old['nombre'] ?? '') ?>" autocomplete="name"></div>
        <div class="mb-3"><label for="email" class="form-label">Correo electrónico</label><input id="email" name="email" type="email" class="form-control" required maxlength="254" value="<?= View::escape($old['email'] ?? '') ?>" autocomplete="email"></div>
        <div class="mb-3"><label for="rol" class="form-label">Rol</label><select id="rol" name="rol" class="form-select" required <?= $self ? 'disabled' : '' ?>><?php foreach (['usuario' => 'Usuario', 'agente' => 'Agente', 'administrador' => 'Administrador'] as $value => $label): ?><option value="<?= $value ?>" <?= ($old['rol'] ?? '') === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select><?php if ($self): ?><input type="hidden" name="rol" value="administrador"><?php endif; ?></div>
        <div class="form-check mb-3"><input type="hidden" name="activo" value="0"><input id="activo" name="activo" type="checkbox" class="form-check-input" value="1" <?= (string) ($old['activo'] ?? '') === '1' ? 'checked' : '' ?> <?= $self ? 'disabled' : '' ?>><label for="activo" class="form-check-label">Cuenta activa</label><?php if ($self): ?><input type="hidden" name="activo" value="1"><?php endif; ?></div>
        <?php if ($self): ?><p class="form-text">No podés quitarte el rol de administrador ni desactivar tu propia cuenta.</p><?php endif; ?>
        <?php if (!$self): ?>
        <div class="mb-4"><label for="password" class="form-label"><?= $id === null ? 'Contraseña inicial' : 'Nueva contraseña (opcional)' ?></label><input id="password" name="password" type="password" class="form-control" minlength="10" maxlength="64" <?= $id === null ? 'required' : '' ?> autocomplete="new-password" aria-describedby="password-help"><p id="password-help" class="form-text">Entre 10 y 64 caracteres UTF-8 y máximo 72 bytes; espacios conservados. <?= $id !== null ? 'Dejá este campo vacío para conservar la contraseña actual.' : '' ?> Comunicá la contraseña inicial o restablecida por un canal externo acordado.</p></div>
        <?php else: ?><p class="mb-4">Para actualizar tu contraseña usá <a href="/cuenta/password">Mi contraseña</a>, verificando la actual.</p><?php endif; ?>
        <button type="submit" class="btn btn-primary">Guardar usuario</button><a href="/usuarios" class="btn btn-outline-secondary ms-2">Cancelar</a>
    </form>
</div></div>
