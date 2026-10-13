<?php

declare(strict_types=1);

namespace InsTickets\Tests\Unit;

use InsTickets\Core\ValidationException;
use InsTickets\Services\ReportFilter;
use InsTickets\Services\ReportMetrics;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReportTest extends TestCase
{
    public function testLocalDaysBecomeInclusiveStartAndExclusiveNextDayUtc(): void
    {
        self::assertSame(['2026-10-12 06:00:00', '2026-10-13 06:00:00'], ReportFilter::utcBounds(['desde' => '2026-10-12', 'hasta' => '2026-10-12']));
        self::assertSame(['2024-02-29 06:00:00', '2024-03-01 06:00:00'], ReportFilter::utcBounds(['desde' => '2024-02-29', 'hasta' => '2024-02-29']));
        self::assertSame([null, null], ReportFilter::utcBounds([]));
        self::assertSame(['2026-10-12 06:00:00', null], ReportFilter::utcBounds(['desde' => '2026-10-12']));
        self::assertSame([null, '2026-10-13 06:00:00'], ReportFilter::utcBounds(['hasta' => '2026-10-12']));
    }

    public static function invalidFilters(): array
    {
        return [
            'inverted' => [['desde' => '2026-10-13', 'hasta' => '2026-10-12']],
            'nonleap' => [['desde' => '2026-02-29']], 'invalid-day' => [['hasta' => '2026-04-31']],
            'date-format' => [['desde' => '12/10/2026']], 'date-injection' => [['hasta' => "2026-10-12' OR 1=1"]],
            'month-zero' => [['desde' => '2026-00-12']], 'too-early' => [['desde' => '0001-01-01']],
            'upper-overflow' => [['hasta' => '9999-12-31']], 'state' => [['estado' => 'reabierto']],
            'priority' => [['prioridad' => 'urgente']], 'category-zero' => [['categoria_id' => '0']],
            'category-sql' => [['categoria_id' => '1 OR 1=1']], 'agent-array' => [['agente_id' => ['1']]],
            'date-array' => [['desde' => ['2026-10-12']]], 'agent-negative' => [['agente_id' => '-1']],
        ];
    }

    #[DataProvider('invalidFilters')]
    public function testInvalidFiltersNeverFallBackToAllTickets(array $input): void
    {
        $this->expectException(ValidationException::class);
        ReportFilter::validate($input);
    }

    public function testUnassignedCombinedFiltersAndIgnoredUnspecifiedFields(): void
    {
        self::assertSame(['desde' => '', 'hasta' => '', 'estado' => 'abierto', 'prioridad' => 'alta', 'categoria_id' => '3', 'agente_id' => 'sin_asignar'], ReportFilter::validate([
            'estado' => ' abierto ', 'prioridad' => 'alta', 'categoria_id' => '3', 'agente_id' => 'sin_asignar', 'resolved_by' => '1', 'q' => 'ignored',
        ]));
    }

    public function testMeansUseSecondsAndExcludeAbsentSamples(): void
    {
        $response = ReportMetrics::mean('14400.000000', 2);
        self::assertSame(7200.0, $response['seconds']);
        self::assertSame(2.0, $response['hours']);
        self::assertSame('2.00 h', $response['label']);
        self::assertSame(2, $response['n']);
        self::assertSame('6.00 h', ReportMetrics::mean(43200, 2)['label']);
        self::assertSame('0.00 h', ReportMetrics::mean(0, 1)['label']);
        self::assertSame('Sin datos', ReportMetrics::mean(null, 0)['label']);
        self::assertSame('Sin datos', ReportMetrics::mean(0, 0)['label']);
        self::assertNull(ReportMetrics::mean(null, 0)['hours']);
        self::assertEqualsWithDelta(0.5, ReportMetrics::mean(1, 2)['seconds'], 0.000001);
    }
}
