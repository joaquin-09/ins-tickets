<?php

declare(strict_types=1);

namespace InsTickets\Controllers;

use InsTickets\Core\Auth;
use InsTickets\Core\Database;
use InsTickets\Core\View;
use InsTickets\Repositories\ReportRepository;

final class HomeController
{
    public function index(): void
    {
        $user = Auth::requireUser();
        $counts = (new ReportRepository(Database::connection()))->panel($user);
        View::render('home/index', ['title' => 'Inicio', 'user' => $user, 'counts' => $counts]);
    }
}
