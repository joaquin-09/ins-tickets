<?php use InsTickets\Core\Auth; use InsTickets\Core\Csrf; use InsTickets\Core\View; use InsTickets\Services\AttentionPolicy; ?>
<?php if (AttentionPolicy::comment(Auth::user(), $ticket)): ?>
<form method="post" action="/tickets/comentar" class="mt-4">
    <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>"><input type="hidden" name="id" value="<?= (int) $ticket['id'] ?>"><input type="hidden" name="version" value="<?= (int) $ticket['version'] ?>">
    <label class="form-label" for="contenido">Nuevo comentario</label><textarea id="contenido" name="contenido" class="form-control" rows="3" minlength="1" maxlength="2000" required><?= View::escape($attentionOld['contenido'] ?? '') ?></textarea>
    <p class="form-text">Texto visible para las personas autorizadas. Los comentarios no se editan ni borran; no incluyás secretos.</p>
    <button class="btn btn-primary" type="submit">Publicar comentario</button>
</form>
<?php endif; ?>
