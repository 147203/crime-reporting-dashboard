<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\CrimeCategory;
use App\Models\Station;
use App\Models\SubCounty;
use App\Models\Ward;
use App\Support\Filters;

/**
 * Turns A1 to A10 results into chart specifications and their table alternatives. Every chart carries a caption with
 * the period, the active filters and n; percentages name their denominator.
 */
final class DashboardViewModel
{
    private const WEEKDAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

    /**
     * @param  array<string, mixed>  $metrics  Analytics::all()
     */
    public function __construct(public readonly array $metrics, public readonly Filters $filters) {}

    public function n(): int
    {
        return (int) $this->metrics['A1']['n'];
    }

    public function periodLabel(): string
    {
        return ($this->filters->from?->format('j M Y') ?? '').' to '.($this->filters->to?->format('j M Y') ?? '');
    }

    private ?string $filterLabel = null;

    public function filterLabel(): string
    {
        return $this->filterLabel ??= $this->describeFilters();
    }

    private function describeFilters(): string
    {
        $parts = array_filter([
            $this->filters->categoryId ? 'Category: '.CrimeCategory::query()->whereKey($this->filters->categoryId)->value('name') : null,
            $this->filters->subCountyId ? 'Sub-county: '.SubCounty::query()->whereKey($this->filters->subCountyId)->value('name') : null,
            $this->filters->wardId ? 'Ward: '.Ward::query()->whereKey($this->filters->wardId)->value('name') : null,
            $this->filters->stationId ? 'Station: '.Station::query()->whereKey($this->filters->stationId)->value('name') : null,
            $this->filters->status ? 'Status: '.str_replace('_', ' ', $this->filters->status) : null,
        ]);

        return $parts === [] ? 'No other filters' : implode('; ', $parts);
    }

    public function caption(?int $n = null): string
    {
        return $this->periodLabel().'. '.$this->filterLabel().'. n = '.number_format($n ?? $this->n()).'.';
    }

    /**
     * @return array<string, mixed>
     */
    public function trend(): array
    {
        $rows = $this->metrics['A3'];
        $labels = array_map(fn (array $r): string => date('M Y', (int) strtotime($r['month'].'-01')), $rows);

        return [
            'spec' => [
                'type' => 'line',
                'labels' => $labels,
                'datasets' => [
                    ['label' => 'Incidents', 'data' => array_column($rows, 'count'), 'kind' => 'line', 'colour' => 0],
                    ['label' => '3-month moving average', 'data' => array_column($rows, 'ma3'), 'kind' => 'line', 'colour' => 1, 'dashed' => true],
                ],
                'yLabel' => 'Incidents',
            ],
            'headers' => ['Month', 'Incidents', '3-month average', 'Change on previous month'],
            'rows' => array_map(fn (array $r, string $label): array => [$label, number_format($r['count']), $r['ma3'] === null ? 'n/a' : number_format($r['ma3'], 2), $r['mom_pct'] === null ? 'n/a' : self::signed($r['mom_pct']).' %'], $rows, $labels),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function categories(): array
    {
        $rows = $this->metrics['A2'];

        return [
            'spec' => ['type' => 'bar', 'horizontal' => true, 'labels' => array_column($rows, 'category'), 'datasets' => [['label' => 'Incidents', 'data' => array_column($rows, 'count')]]],
            'headers' => ['Category', 'Incidents', 'Share of n (%)'],
            'rows' => array_map(fn (array $r): array => [$r['category'], number_format($r['count']), number_format($r['share_pct'], 2)], $rows),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function weekdays(): array
    {
        $rows = $this->metrics['A5'];

        return [
            'spec' => ['type' => 'bar', 'labels' => array_map(fn (array $r): string => substr(self::WEEKDAYS[$r['weekday']], 0, 3), $rows), 'datasets' => [['label' => 'Incidents', 'data' => array_column($rows, 'count'), 'colour' => 2]]],
            'headers' => ['Day', 'Incidents', 'Share of n (%)'],
            'rows' => array_map(fn (array $r): array => [self::WEEKDAYS[$r['weekday']], number_format($r['count']), number_format($r['share_pct'], 2)], $rows),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function timeBands(): array
    {
        $rows = $this->metrics['A6'];

        return [
            'spec' => ['type' => 'bar', 'labels' => array_column($rows, 'band'), 'datasets' => [['label' => 'Incidents', 'data' => array_column($rows, 'count'), 'colour' => 3]]],
            'headers' => ['Time of day', 'Incidents', 'Share of n (%)'],
            'rows' => array_map(fn (array $r): array => [$r['band'], number_format($r['count']), number_format($r['share_pct'], 2)], $rows),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function topWards(int $limit = 10): array
    {
        $rows = array_slice($this->metrics['A7']['wards'], 0, $limit);

        return [
            'spec' => ['type' => 'bar', 'horizontal' => true, 'labels' => array_column($rows, 'name'), 'datasets' => [['label' => 'Incidents', 'data' => array_column($rows, 'count'), 'colour' => 4]]],
            'headers' => ['Rank', 'Ward', 'Incidents', 'Share of n (%)'],
            'rows' => array_map(fn (array $r): array => [$r['rank'], $r['name'], number_format($r['count']), number_format($r['share_pct'], 2)], $rows),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function statuses(): array
    {
        $status = $this->metrics['A10']['status'];
        $labels = ['reported' => 'Reported', 'under_investigation' => 'Under investigation', 'closed' => 'Closed'];

        return [
            'spec' => ['type' => 'doughnut', 'labels' => array_values($labels), 'datasets' => [['label' => 'Incidents', 'data' => array_values($status)]]],
            'headers' => ['Status', 'Incidents', 'Share of n (%)'],
            'rows' => array_map(fn (string $key): array => [$labels[$key], number_format($status[$key]), $this->n() > 0 ? number_format(round(100 * $status[$key] / $this->n(), 2), 2) : '0.00'], array_keys($labels)),
        ];
    }

    /**
     * A9 cells with a quintile class (0 for zero, then 1 to 5 by the quintiles of the non-zero cells).
     *
     * @return array{months: list<string>, rows: list<array{sub_county: string, cells: list<array{count: int, level: int}>, total: int}>}
     */
    public function heatGrid(): array
    {
        $grid = $this->metrics['A9'];
        $values = [];
        foreach ($grid['rows'] as $row) {
            foreach ($row['counts'] as $count) {
                if ($count > 0) {
                    $values[] = $count;
                }
            }
        }
        sort($values);
        $cuts = [];
        foreach ([0.2, 0.4, 0.6, 0.8] as $q) {
            $cuts[] = $values === [] ? 0 : $values[(int) floor($q * (count($values) - 1))];
        }

        $level = function (int $count) use ($cuts): int {
            if ($count === 0) {
                return 0;
            }
            $level = 1;
            foreach ($cuts as $cut) {
                if ($count > $cut) {
                    $level++;
                }
            }

            return $level;
        };

        return [
            'months' => array_values(array_map(fn (string $m): string => date('M y', (int) strtotime($m.'-01')), $grid['months'])),
            'rows' => array_values(array_map(fn (array $row): array => [
                'sub_county' => (string) $row['sub_county'],
                'cells' => array_values(array_map(fn (int $c): array => ['count' => $c, 'level' => $level($c)], $row['counts'])),
                'total' => (int) array_sum($row['counts']),
            ], $grid['rows'])),
        ];
    }

    public static function signed(float $value): string
    {
        return ($value > 0 ? '+' : '').number_format($value, 2);
    }
}
