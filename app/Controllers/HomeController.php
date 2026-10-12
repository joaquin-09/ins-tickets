<?php

declare(strict_types=1);

namespace InsTickets\Controllers;

use InsTickets\Core\View;

final class HomeController
{
    public function index(): void
    {
        View::render('home/index', ['title' => 'INS Tickets']);
    }
}
