<?php

declare(strict_types=1);

namespace InsTickets\Core;

final class View
{
    public static function render(string $view, array $data = [], int $status = 200): void
    {
        http_response_code($status);
        extract($data, EXTR_SKIP);
        ob_start();
        require Config::root() . '/app/Views/' . $view . '.php';
        $content = ob_get_clean();
        require Config::root() . '/app/Views/layouts/base.php';
    }

    public static function escape(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
