<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Enums\IncidentStatus;
use App\Models\CrimeCategory;
use App\Models\Incident;
use App\Models\SubCounty;
use App\Models\User;
use App\Models\Ward;
use App\Support\Filters;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * Analytics catalogue A1 to A10 (docs/02 section 4, clarifications D-27). Every metric is an aggregate SQL query over
 * the same base: countable incidents, the viewer's role scope, then the filters. Results use the same shape as the
 * independent oracle (scripts/oracle.php) so the accuracy tests compare them directly.
 *
 * Rounding: percentages round(100 * part / whole, 2); z scores, mean and sigma 4 dp; hours and days 2 dp.
 */
final class Analytics
{
    /** Day of week 1 (Monday) to 7, shared with the incident list's drill-down filter so both count the same way. */
    public const WEEKDAY_SQL = 'WEEKDAY(incidents.occurred_at) + 1';

    /** Hour of day, shared with the incident list's time-band filter. */
    public const HOUR_SQL = 'HOUR(incidents.occurred_at)';

    public function __construct(private readonly User $viewer, private readonly Filters $filters)
    {
        if ($filters->from === null || $filters->to === null) {
            throw new InvalidArgumentException('Analytics need a period (from and to).');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $n = $this->a1()['n'];

        return [
            'A1' => ['n' => $n],
            'A2' => $this->a2($n),
            'A3' => $this->a3(),
            'A4' => $this->a4($n),
            'A5' => $this->a5($n),
            'A6' => $this->a6($n),
            'A7' => $this->a7($n),
            'A8' => $this->a8(),
            'A9' => $this->a9(),
            'A10' => $this->a10($n),
        ];
    }

    /**
     * @return array{n: int}
     */
    public function a1(): array
    {
        return ['n' => $this->base()->count()];
    }

    /**
     * A2 category distribution: categories present, by count then name.
     *
     * @return list<array{category: string, count: int, share_pct: float}>
     */
    public function a2(int $n): array
    {
        $counts = $this->countsBy('incidents.category_id');
        $names = CrimeCategory::query()->whereIn('id', array_keys($counts))->pluck('name', 'id');

        $rows = [];
        foreach ($counts as $id => $count) {
            $rows[] = ['category' => (string) $names[$id], 'count' => $count, 'share_pct' => self::pct($count, $n)];
        }
        usort($rows, fn (array $a, array $b): int => [$b['count'], $a['category']] <=> [$a['count'], $b['category']]);

        return $rows;
    }

    /**
     * A3 monthly trend with a 3-month moving average and month-on-month change.
     *
     * @return list<array{month: string, count: int, ma3: float|null, mom_pct: float|null}>
     */
    public function a3(): array
    {
        $counts = $this->countsBy("DATE_FORMAT(incidents.occurred_at, '%Y-%m')");
        $rows = [];
        $series = [];

        foreach ($this->months() as $i => $month) {
            $count = $counts[$month] ?? 0;
            $series[] = $count;
            $previous = $series[$i - 1] ?? null;
            $rows[] = [
                'month' => $month,
                'count' => $count,
                'ma3' => $i >= 2 ? round(($series[$i] + $series[$i - 1] + $series[$i - 2]) / 3, 2) : null,
                'mom_pct' => $previous !== null && $previous > 0 ? round(100 * ($count - $previous) / $previous, 2) : null,
            ];
        }

        return $rows;
    }

    /**
     * A4 period comparison with the immediately preceding period of equal length (days, inclusive).
     *
     * @return array{current: int, previous: int, change: int, change_pct: float|null}
     */
    public function a4(int $n): array
    {
        [$from, $to] = $this->period();
        $days = (int) $from->startOfDay()->diffInDays($to->startOfDay()) + 1;
        $previousFilters = $this->filters->withPeriod($from->subDays($days)->startOfDay(), $from->subDay()->endOfDay());
        $previous = (new self($this->viewer, $previousFilters))->a1()['n'];

        return [
            'current' => $n,
            'previous' => $previous,
            'change' => $n - $previous,
            'change_pct' => $previous > 0 ? round(100 * ($n - $previous) / $previous, 2) : null,
        ];
    }

    /**
     * A5 ISO weekday pattern, Monday (1) to Sunday (7).
     *
     * @return list<array{weekday: int, count: int, share_pct: float}>
     */
    public function a5(int $n): array
    {
        $counts = $this->countsBy(self::WEEKDAY_SQL);

        return array_map(fn (int $day): array => ['weekday' => $day, 'count' => $counts[$day] ?? 0, 'share_pct' => self::pct($counts[$day] ?? 0, $n, zero: 0.0)], range(1, 7));
    }

    /**
     * A6 time-of-day bands (config analytics.time_bands).
     *
     * @return list<array{band: string, count: int, share_pct: float}>
     */
    public function a6(int $n): array
    {
        $bands = config('analytics.time_bands');
        $cases = [];
        $bindings = [];
        foreach ($bands as $index => $band) {
            $cases[] = 'WHEN '.self::HOUR_SQL.' BETWEEN ? AND ? THEN '.(int) $index;
            array_push($bindings, (int) $band['from'], (int) $band['to']);
        }
        $counts = $this->base()->toBase()
            ->selectRaw('CASE '.implode(' ', $cases).' END as bucket, count(*) as n', $bindings)
            ->groupBy('bucket')
            ->pluck('n', 'bucket')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
        $rows = [];
        foreach ($bands as $index => $band) {
            $rows[] = ['band' => (string) $band['label'], 'count' => $counts[$index] ?? 0, 'share_pct' => self::pct($counts[$index] ?? 0, $n, zero: 0.0)];
        }

        return $rows;
    }

    /**
     * A7 area ranking per ward and per sub-county, zero-count areas included. Competition ranking (ties share a rank).
     *
     * @return array{wards: list<array{id: int, name: string, count: int, share_pct: float, rank: int}>, sub_counties: list<array{id: int, name: string, count: int, share_pct: float, rank: int}>}
     */
    public function a7(int $n): array
    {
        $wardCounts = $this->countsBy('incidents.ward_id');
        $wards = $this->wardUniverse(applyWardFilter: true)->pluck('name', 'id');

        $subCountyCounts = $this->countsBy('incidents.sub_county_id');
        $subCounties = $this->subCountyUniverse(narrowToFilteredWard: true)->pluck('name', 'id');

        return [
            'wards' => self::ranked($wards->all(), $wardCounts, $n),
            'sub_counties' => self::ranked($subCounties->all(), $subCountyCounts, $n),
        ];
    }

    /**
     * A8 non-spatial hotspot flag: z score of each ward's count against all wards (zeros included), flagged when
     * z >= threshold and count >= minimum. The ward filter is ignored, because one ward has no spread.
     *
     * @return array{mu: float, sigma: float, flagged: list<array{id: int, ward: string, count: int, z: float}>, threshold: float, min_incidents: int}
     */
    public function a8(): array
    {
        $threshold = (float) config('analytics.hotspot.z_threshold');
        $minimum = (int) config('analytics.hotspot.min_incidents');
        $withoutWard = new self($this->viewer, $this->filters->withoutWard());
        $counts = $withoutWard->countsBy('incidents.ward_id');
        $wards = $this->wardUniverse(applyWardFilter: false)->pluck('name', 'id');

        // Keyed by id: ward names are unique only within a sub-county.
        $values = [];
        foreach ($wards as $id => $name) {
            $values[(int) $id] = $counts[$id] ?? 0;
        }

        $size = count($values);
        $mu = $size > 0 ? array_sum($values) / $size : 0.0;
        $sigma = $size > 0 ? sqrt(array_sum(array_map(fn (int $c): float => ($c - $mu) ** 2, $values)) / $size) : 0.0;

        $flagged = [];
        if ($sigma > 0) {
            foreach ($values as $id => $count) {
                $z = ($count - $mu) / $sigma;
                if ($z >= $threshold && $count >= $minimum) {
                    $flagged[] = ['id' => $id, 'ward' => (string) $wards[$id], 'count' => $count, 'z_exact' => $z];
                }
            }
        }
        usort($flagged, fn (array $a, array $b): int => [$b['z_exact'], $a['ward']] <=> [$a['z_exact'], $b['ward']]);

        return [
            'mu' => round($mu, 4),
            'sigma' => round($sigma, 4),
            'flagged' => array_map(fn (array $f): array => ['id' => $f['id'], 'ward' => $f['ward'], 'count' => $f['count'], 'z' => round($f['z_exact'], 4)], $flagged),
            'threshold' => $threshold,
            'min_incidents' => $minimum,
        ];
    }

    /**
     * A9 area by time grid: sub-county by month counts (a table, not a map).
     *
     * @return array{months: list<string>, rows: list<array{sub_county: string, counts: list<int>}>}
     */
    public function a9(): array
    {
        $months = $this->months();
        $cells = [];
        foreach ($this->base()->toBase()
            ->selectRaw("incidents.sub_county_id as area, DATE_FORMAT(incidents.occurred_at, '%Y-%m') as month, count(*) as n")
            ->groupBy('area', 'month')->get() as $row) {
            $cells[(int) $row->area][(string) $row->month] = (int) $row->n;
        }

        $rows = [];
        foreach ($this->subCountyUniverse(narrowToFilteredWard: false)->pluck('name', 'id') as $id => $name) {
            $rows[] = ['sub_county' => (string) $name, 'counts' => array_map(fn (string $m): int => $cells[$id][$m] ?? 0, $months)];
        }

        return ['months' => $months, 'rows' => $rows];
    }

    /**
     * A10 status and timeliness. Percentiles are nearest-rank (PERCENTILE_DISC): the median of an even count is the
     * lower middle value.
     *
     * @return array{status: array<string, int>, closure_rate_pct: float|null, report_delay_hours: array{median: float|null, p90: float|null}, closure_days: array{median: float|null}}
     */
    public function a10(int $n): array
    {
        $byStatus = $this->countsBy('incidents.status');
        $status = [];
        foreach (IncidentStatus::cases() as $case) {
            $status[$case->value] = (int) ($byStatus[$case->value] ?? 0);
        }

        $delay = $this->base()->toBase()->selectRaw(
            'PERCENTILE_DISC(0.5) WITHIN GROUP (ORDER BY TIMESTAMPDIFF(MINUTE, incidents.occurred_at, incidents.reported_at)) OVER () as p50, '
            .'PERCENTILE_DISC(0.9) WITHIN GROUP (ORDER BY TIMESTAMPDIFF(MINUTE, incidents.occurred_at, incidents.reported_at)) OVER () as p90'
        )->limit(1)->first();

        $closure = $this->base()->where('incidents.status', IncidentStatus::Closed->value)->toBase()->selectRaw(
            'PERCENTILE_DISC(0.5) WITHIN GROUP (ORDER BY TIMESTAMPDIFF(MINUTE, incidents.reported_at, incidents.closed_at)) OVER () as p50'
        )->limit(1)->first();

        return [
            'status' => $status,
            'closure_rate_pct' => $n > 0 ? round(100 * $status[IncidentStatus::Closed->value] / $n, 2) : null,
            'report_delay_hours' => [
                'median' => self::minutesTo($delay->p50 ?? null, 60),
                'p90' => self::minutesTo($delay->p90 ?? null, 60),
            ],
            'closure_days' => ['median' => self::minutesTo($closure->p50 ?? null, 1440)],
        ];
    }

    /**
     * Countable incidents, the viewer's scope, then the filters. Every metric starts here.
     *
     * @return Builder<Incident>
     */
    public function base(): Builder
    {
        return $this->filters->apply(Incident::query()->countable()->visibleTo($this->viewer));
    }

    /**
     * @return list<string> YYYY-MM from the month of `from` to the month of `to`
     */
    public function months(): array
    {
        [$from, $to] = $this->period();
        $months = [];
        for ($cursor = $from->startOfMonth(); $cursor->lessThanOrEqualTo($to); $cursor = $cursor->addMonthNoOverflow()) {
            $months[] = $cursor->format('Y-m');
        }

        return $months;
    }

    /**
     * @return array<int|string, int>
     */
    private function countsBy(string $expression): array
    {
        return $this->base()->toBase()
            ->selectRaw("{$expression} as bucket, count(*) as n")
            ->groupByRaw($expression)
            ->pluck('n', 'bucket')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
    }

    /**
     * @return Builder<Ward>
     */
    private function wardUniverse(bool $applyWardFilter): Builder
    {
        return Ward::query()
            ->when($this->filters->subCountyId, fn ($q, int $id) => $q->where('sub_county_id', $id))
            ->when($applyWardFilter ? $this->filters->wardId : null, fn ($q, int $id) => $q->whereKey($id));
    }

    /**
     * @return Builder<SubCounty>
     */
    private function subCountyUniverse(bool $narrowToFilteredWard): Builder
    {
        $wardSubCounty = $narrowToFilteredWard && $this->filters->wardId !== null
            ? Ward::query()->whereKey($this->filters->wardId)->value('sub_county_id')
            : null;

        return SubCounty::query()
            ->when($this->filters->subCountyId, fn ($q, int $id) => $q->whereKey($id))
            ->when($wardSubCounty, fn ($q, int $id) => $q->whereKey($id));
    }

    /**
     * @param  array<int, string>  $names  id => name
     * @param  array<int|string, int>  $counts  id => count
     * @return list<array{id: int, name: string, count: int, share_pct: float, rank: int}>
     */
    private static function ranked(array $names, array $counts, int $n): array
    {
        $rows = [];
        foreach ($names as $id => $name) {
            $rows[] = ['id' => (int) $id, 'name' => $name, 'count' => $counts[$id] ?? 0, 'share_pct' => self::pct($counts[$id] ?? 0, $n, zero: 0.0), 'rank' => 0];
        }
        usort($rows, fn (array $a, array $b): int => [$b['count'], $a['name']] <=> [$a['count'], $b['name']]);

        foreach ($rows as $i => $row) {
            $rows[$i]['rank'] = 1 + count(array_filter($rows, fn (array $other): bool => $other['count'] > $row['count']));
        }

        return $rows;
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function period(): array
    {
        return [$this->filters->from ?? throw new InvalidArgumentException('No start date.'), $this->filters->to ?? throw new InvalidArgumentException('No end date.')];
    }

    private static function pct(int $part, int $whole, ?float $zero = null): float
    {
        return $whole > 0 ? round(100 * $part / $whole, 2) : ($zero ?? 0.0);
    }

    private static function minutesTo(mixed $minutes, int $divisor): ?float
    {
        return $minutes === null ? null : round(((int) $minutes) / $divisor, 2);
    }
}
