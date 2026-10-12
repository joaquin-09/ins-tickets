<?php

declare(strict_types=1);

namespace InsTickets\Controllers;

use InsTickets\Core\View;
use InsTickets\Core\Auth;

final class HomeController
{
    public function index(): void
    {
        Auth::requireUser();
        View::render('home/index', ['title' => 'Inicio']);
    }
}
