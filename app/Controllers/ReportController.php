<?php

declare(strict_types=1);

namespace InsTickets\Controllers;

use InsTickets\Core\Auth;
use InsTickets\Core\Database;
use InsTickets\Core\Input;
use InsTickets\Core\ValidationException;
use InsTickets\Core\View;
use InsTickets\Repositories\ReportRepository;
use InsTickets\Repositories\TicketRepository;
use InsTickets\Services\ReportFilter;

final class ReportController
{
    public function index(): void
    {
        $actor = Auth::requireAdmin();
        $pdo = Database::connection();
        $report = null;
        $errors = [];
        $filters = Input::old($_GET, ReportFilter::KEYS);
        $pages = [];
        foreach (['categorias', 'agentes', 'resolutores'] as $key) {
            $pages[$key] = Input::page($_GET[$key . '_pagina'] ?? 1);
        }
        try {
            $report = (new ReportRepository($pdo))->report($actor, $_GET, $pages);
            $filters = $report['filters'];
        } catch (ValidationException $exception) {
            $errors = $exception->errors;
        }
        $tickets = new TicketRepository($pdo);
        View::render('reports/index', ['title' => 'Reportes', 'report' => $report,
            'filters' => $filters, 'errors' => $errors, 'pages' => $pages,
            'categories' => $tickets->categories(), 'users' => (new ReportRepository($pdo))->agentsForFilters()], $errors === [] ? 200 : 422);
    }
}
