<?php use InsTickets\Core\View; use InsTickets\Services\TicketValidation; ?>
<div class="row g-3 mb-4">
    <?php foreach (['total' => 'Total de tickets'] + TicketValidation::STATES as $key => $label): ?>
        <div class="col-6 col-lg"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <p class="text-secondary mb-1"><?= View::escape($label) ?></p><p class="h2 mb-0"><?= (int) $counts[$key] ?></p>
        </div></div></div>
    <?php endforeach; ?>
</div>
<section class="card border-0 shadow-sm mb-4"><div class="card-body">
    <h2 class="h5">Tickets por prioridad</h2>
    <div class="table-responsive"><table class="table mb-0"><caption class="visually-hidden">Conteos por prioridad del conjunto indicado</caption>
        <thead><tr><th scope="col">Prioridad</th><th scope="col">Tickets</th></tr></thead><tbody>
        <?php foreach (TicketValidation::PRIORITIES as $key => $label): ?><tr><th scope="row"><?= View::escape($label) ?></th><td><?= (int) $counts[$key] ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
</div></section>
