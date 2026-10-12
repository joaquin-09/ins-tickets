<?php

declare(strict_types=1);

namespace InsTickets\Core;

use DateTimeImmutable;
use DateTimeZone;

final class Dates
{
    public static function local(?string $utc): string
    {
        return $utc === null ? 'Sin datos' : (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('America/El_Salvador'))->format('d/m/Y H:i');
    }

    public static function code(int $id): string
    {
        return 'TCK-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }
}
