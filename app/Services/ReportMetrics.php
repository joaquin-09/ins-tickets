<?php

declare(strict_types=1);

namespace InsTickets\Services;

final class ReportMetrics
{
    public static function mean(mixed $seconds, int $count): array
    {
        if ($count <= 0 || $seconds === null) {
            return ['seconds' => null, 'hours' => null, 'n' => 0, 'label' => 'Sin datos'];
        }
        $mean = (float) $seconds / $count;
        return ['seconds' => $mean, 'hours' => $mean / 3600, 'n' => $count, 'label' => number_format($mean / 3600, 2, '.', '') . ' h'];
    }
}
