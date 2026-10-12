<?php use InsTickets\Core\Auth; use InsTickets\Core\Csrf; use InsTickets\Core\View; use InsTickets\Services\AttentionPolicy; $actor = Auth::requireUser(); $attentionOld ??= []; ?>
<section class="card border-0 shadow-sm mb-4"><div class="card-body p-4"><h2 class="h5">Atención</h2>
    <?php $errors = $attentionErrors ?? []; require dirname(__DIR__) . '/partials/errors.php'; ?>
    <?php if (AttentionPolicy::assign($actor, $ticket)): ?>
    <form method="post" action="/tickets/asignar" class="mb-4">
        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>"><input type="hidden" name="id" value="<?= (int) $ticket['id'] ?>"><input type="hidden" name="version" value="<?= (int) $ticket['version'] ?>">
        <label class="form-label" for="agente_id">Asignar o reasignar agente</label>
        <div class="d-flex gap-2 flex-wrap"><select id="agente_id" name="agente_id" class="form-select assignment-select">
            <option value="" <?= $ticket['estado'] === 'en_progreso' ? 'disabled' : '' ?>><?= $ticket['estado'] === 'en_progreso' ? 'Seleccionar agente activo' : 'Sin asignar' ?></option>
            <?php foreach ($agents ?? [] as $agent): ?><?php if ($agent['rol'] === 'agente' && $agent['activo'] && (int) $agent['id'] !== (int) $ticket['solicitante_id']): ?>
            <option value="<?= (int) $agent['id'] ?>" <?= (string) ($attentionOld['agente_id'] ?? $ticket['agente_id'] ?? '') === (string) $agent['id'] ? 'selected' : '' ?>><?= View::escape($agent['nombre']) ?></option>
            <?php endif; ?><?php endforeach; ?>
        </select><button class="btn btn-primary" type="submit">Guardar asignación</button></div>
        <p class="form-text">La asignación conserva estado y tiempos. Un ticket en progreso siempre mantiene un agente.</p>
    </form>
    <?php endif; ?>
    <?php if (AttentionPolicy::start($actor, $ticket)): ?>
    <form method="post" action="/tickets/transicion" class="mb-3">
        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>"><input type="hidden" name="id" value="<?= (int) $ticket['id'] ?>"><input type="hidden" name="version" value="<?= (int) $ticket['version'] ?>"><input type="hidden" name="estado" value="en_progreso">
        <button class="btn btn-primary" type="submit">Iniciar atención</button>
    </form>
    <?php endif; ?>
    <?php if (AttentionPolicy::resolve($actor, $ticket)): ?>
    <form method="post" action="/tickets/transicion" class="mb-3">
        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>"><input type="hidden" name="id" value="<?= (int) $ticket['id'] ?>"><input type="hidden" name="version" value="<?= (int) $ticket['version'] ?>"><input type="hidden" name="estado" value="resuelto">
        <label class="form-label" for="resolution_summary">Resumen de resolución</label><textarea id="resolution_summary" name="resolution_summary" class="form-control mb-2" rows="3" required minlength="10" maxlength="2000"><?= View::escape($attentionOld['resolution_summary'] ?? '') ?></textarea>
        <p class="form-text">Explicá la solución en 10–2,000 caracteres. Se conserva como resolución; no se duplica como comentario.</p>
        <button class="btn btn-success" type="submit">Marcar como resuelto</button>
    </form>
    <?php endif; ?>
    <?php if (AttentionPolicy::close($actor, $ticket)): ?>
    <form method="post" action="/tickets/transicion" class="mb-3">
        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>"><input type="hidden" name="id" value="<?= (int) $ticket['id'] ?>"><input type="hidden" name="version" value="<?= (int) $ticket['version'] ?>"><input type="hidden" name="estado" value="cerrado">
        <p>El cierre finaliza la comunicación. No permite reapertura ni exige comentario de conformidad.</p><button class="btn btn-primary" type="submit">Cerrar ticket resuelto</button>
    </form>
    <?php endif; ?>
    <?php if ($ticket['estado'] === 'cerrado'): ?><p class="text-secondary mb-0">Ticket cerrado: consulta únicamente.</p>
    <?php elseif ($ticket['estado'] === 'abierto' && $ticket['agente_id'] === null): ?><p class="text-secondary mb-0">Pendiente de asignación administrativa antes de iniciar atención.</p>
    <?php elseif (!AttentionPolicy::start($actor, $ticket) && !AttentionPolicy::resolve($actor, $ticket) && !AttentionPolicy::close($actor, $ticket)): ?><p class="text-secondary mb-0">Podés consultar el seguimiento y comentar; las acciones de atención corresponden al personal autorizado.</p><?php endif; ?>
</div></section>
