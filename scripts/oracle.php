<?php

declare(strict_types=1);

/*
 * Independent accuracy oracle for analytics A1 to A10.
 *
 * Reads the generated sample CSV, the ward reference list and the filter
 * cases, and recomputes every metric with plain PHP arrays. It never loads
 * the application, its database or its query code; see scripts/ORACLE.md.
 *
 * Usage:
 *   php scripts/oracle.php --input <dataset dir> --filters <filters.json> --out <file.json>
 */

const INCIDENT_HEADER = [
    'category', 'occurred_at', 'reported_at', 'sub_county', 'ward', 'station',
    'location_description', 'description', 'status', 'closed_at',
];
const STATUSES = ['reported', 'under_investigation', 'closed'];
const BANDS = [
    ['00:00 to 05:59', 0, 5],
    ['06:00 to 11:59', 6, 11],
    ['12:00 to 17:59', 12, 17],
    ['18:00 to 23:59', 18, 23],
];
const HOTSPOT_Z_THRESHOLD = 1.5;
const HOTSPOT_MIN_INCIDENTS = 10;
const WARDS_CSV = __DIR__.'/../database/seeders/data/wards.csv';

function fail(string $message): never
{
    fwrite(STDERR, "oracle: {$message}\n");
    exit(1);
}

/** @return array<string, string> */
function parseArgs(array $argv): array
{
    $args = [];
    for ($i = 1; $i < count($argv); $i++) {
        if (! str_starts_with($argv[$i], '--') || ! isset($argv[$i + 1])) {
            fail("unexpected argument {$argv[$i]}");
        }
        $args[substr($argv[$i], 2)] = $argv[++$i];
    }
    foreach (['input', 'filters', 'out'] as $required) {
        if (! isset($args[$required])) {
            fail("missing --{$required}");
        }
    }

    return $args;
}

/** @return list<list<string>> rows without the header */
function readCsv(string $path, array $expectedHeader, bool $skipComments = false): array
{
    $fh = fopen($path, 'rb');
    if ($fh === false) {
        fail("cannot open {$path}");
    }
    $rows = [];
    $header = null;
    // Empty escape character gives strict RFC 4180 parsing (quotes escaped only by doubling).
    while (($row = fgetcsv($fh, null, ',', '"', '')) !== false) {
        if ($row === [null]) {
            continue;
        }
        if ($skipComments && str_starts_with((string) $row[0], '#')) {
            continue;
        }
        if ($header === null) {
            $header = $row;
            if ($header !== $expectedHeader) {
                fail("unexpected header in {$path}: ".implode(',', $header));
            }

            continue;
        }
        if (count($row) !== count($expectedHeader)) {
            fail("wrong column count in {$path} data row ".(count($rows) + 1));
        }
        $rows[] = $row;
    }
    fclose($fh);

    return $rows;
}

/** Minutes since the Unix epoch, treating the value as a naive wall-clock time. */
function toMinutes(string $value): int
{
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $value, new DateTimeZone('UTC'));
    if ($dt === false || $dt->format('Y-m-d H:i') !== $value) {
        fail("bad datetime '{$value}' in an accepted row");
    }

    return intdiv($dt->getTimestamp(), 60);
}

function day(string $ymd): DateTimeImmutable
{
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $ymd, new DateTimeZone('UTC'));
    if ($dt === false || $dt->format('Y-m-d') !== $ymd) {
        fail("bad filter date '{$ymd}'");
    }

    return $dt;
}

function pct(int $part, int $whole): ?float
{
    return $whole === 0 ? null : round(100 * $part / $whole, 2);
}

/** Nearest-rank percentile: element at 1-based position ceil(p * count), p = num/den, in integer arithmetic. */
function nearestRank(array $sortedValues, int $num, int $den): ?int
{
    $count = count($sortedValues);
    if ($count === 0) {
        return null;
    }
    $rank = intdiv($num * $count + $den - 1, $den);

    return $sortedValues[max(1, $rank) - 1];
}

/**
 * Rows matching every filter of a case. Keys to skip (e.g. "ward" for A8)
 * are passed in $ignore; from/to may be overridden for A4.
 */
function select(array $rows, array $filters, array $ignore = [], ?string $from = null, ?string $to = null): array
{
    $lo = ($from ?? $filters['from']).' 00:00';
    $hi = ($to ?? $filters['to']).' 23:59';
    $out = [];
    foreach ($rows as $r) {
        // Fixed-width "Y-m-d H:i" strings order the same as the instants they denote.
        if ($r['occurred_at'] < $lo || $r['occurred_at'] > $hi) {
            continue;
        }
        foreach (['category', 'sub_county', 'ward', 'station', 'status'] as $key) {
            if (isset($filters[$key]) && ! in_array($key, $ignore, true) && $r[$key] !== $filters[$key]) {
                continue 2;
            }
        }
        $out[] = $r;
    }

    return $out;
}

/** @return list<string> "YYYY-MM" from the month of $from to the month of $to */
function monthList(string $from, string $to): array
{
    $cursor = day(substr($from, 0, 7).'-01');
    $last = substr($to, 0, 7);
    $months = [];
    while ($cursor->format('Y-m') <= $last) {
        $months[] = $cursor->format('Y-m');
        $cursor = $cursor->modify('first day of next month');
    }

    return $months;
}

function countBy(array $rows, string $key): array
{
    $counts = [];
    foreach ($rows as $r) {
        $counts[$r[$key]] = ($counts[$r[$key]] ?? 0) + 1;
    }

    return $counts;
}

/** Count, share and competition rank over a fixed universe of names (zeros included). */
function ranking(array $universe, array $counts, int $n): array
{
    $entries = [];
    foreach ($universe as $name) {
        $entries[] = ['name' => $name, 'count' => $counts[$name] ?? 0];
    }
    $result = [];
    foreach ($entries as $e) {
        $greater = 0;
        foreach ($entries as $other) {
            if ($other['count'] > $e['count']) {
                $greater++;
            }
        }
        $result[] = [
            'name' => $e['name'],
            'count' => $e['count'],
            'share_pct' => pct($e['count'], $n) ?? 0.0,
            'rank' => 1 + $greater,
        ];
    }
    usort($result, static fn (array $a, array $b): int => $b['count'] <=> $a['count'] ?: strcmp($a['name'], $b['name']));

    return $result;
}

function metrics(array $rows, array $filters, array $wardToSub): array
{
    $pop = select($rows, $filters);
    $n = count($pop);

    // A2
    $a2 = [];
    foreach (countBy($pop, 'category') as $category => $c) {
        $a2[] = ['category' => (string) $category, 'count' => $c, 'share_pct' => pct($c, $n)];
    }
    usort($a2, static fn (array $a, array $b): int => $b['count'] <=> $a['count'] ?: strcmp($a['category'], $b['category']));

    // A3
    $months = monthList($filters['from'], $filters['to']);
    $byMonth = array_fill_keys($months, 0);
    foreach ($pop as $r) {
        $byMonth[substr($r['occurred_at'], 0, 7)]++;
    }
    $a3 = [];
    foreach ($months as $i => $m) {
        $c = $byMonth[$m];
        $prev = $i > 0 ? $byMonth[$months[$i - 1]] : null;
        $a3[] = [
            'month' => $m,
            'count' => $c,
            'ma3' => $i >= 2 ? round(($c + $byMonth[$months[$i - 1]] + $byMonth[$months[$i - 2]]) / 3, 2) : null,
            'mom_pct' => ($prev === null || $prev === 0) ? null : round(100 * ($c - $prev) / $prev, 2),
        ];
    }

    // A4
    $fromDay = day($filters['from']);
    $length = (int) $fromDay->diff(day($filters['to']))->days + 1;
    $prevFrom = $fromDay->modify("-{$length} days")->format('Y-m-d');
    $prevTo = $fromDay->modify('-1 day')->format('Y-m-d');
    $p = count(select($rows, $filters, [], $prevFrom, $prevTo));
    $a4 = ['current' => $n, 'previous' => $p, 'change' => $n - $p, 'change_pct' => $p === 0 ? null : round(100 * ($n - $p) / $p, 2)];

    // A5
    $byWeekday = array_fill(1, 7, 0);
    foreach ($pop as $r) {
        $byWeekday[(int) day(substr($r['occurred_at'], 0, 10))->format('N')]++;
    }
    $a5 = [];
    foreach ($byWeekday as $d => $c) {
        $a5[] = ['weekday' => $d, 'count' => $c, 'share_pct' => pct($c, $n) ?? 0.0];
    }

    // A6
    $a6 = [];
    foreach (BANDS as [$label, $startHour, $endHour]) {
        $c = 0;
        foreach ($pop as $r) {
            $hour = (int) substr($r['occurred_at'], 11, 2);
            if ($hour >= $startHour && $hour <= $endHour) {
                $c++;
            }
        }
        $a6[] = ['band' => $label, 'count' => $c, 'share_pct' => pct($c, $n) ?? 0.0];
    }

    // Area universes (A7, A9): restricted by sub_county and, for A7, by ward.
    $allWards = array_keys($wardToSub);
    $subUniverse = array_values(array_unique(array_values($wardToSub)));
    if (isset($filters['sub_county'])) {
        $subUniverse = array_values(array_filter($subUniverse, static fn (string $s): bool => $s === $filters['sub_county']));
    }
    $wardUniverseA8 = array_values(array_filter($allWards, static fn (string $w): bool => in_array($wardToSub[$w], $subUniverse, true)));
    $wardUniverseA7 = $wardUniverseA8;
    $subUniverseA7 = $subUniverse;
    if (isset($filters['ward'])) {
        $wardUniverseA7 = array_values(array_filter($wardUniverseA7, static fn (string $w): bool => $w === $filters['ward']));
        $wardSub = $wardToSub[$filters['ward']] ?? null;
        $subUniverseA7 = array_values(array_filter($subUniverseA7, static fn (string $s): bool => $s === $wardSub));
    }
    sort($subUniverse, SORT_STRING);

    // A7
    $a7 = [
        'wards' => ranking($wardUniverseA7, countBy($pop, 'ward'), $n),
        'sub_counties' => ranking($subUniverseA7, countBy($pop, 'sub_county'), $n),
    ];

    // A8: ward filter ignored, all other filters kept.
    $wardCounts = countBy(select($rows, $filters, ['ward']), 'ward');
    $values = [];
    foreach ($wardUniverseA8 as $w) {
        $values[$w] = $wardCounts[$w] ?? 0;
    }
    $count = count($values);
    $mu = $count === 0 ? 0.0 : array_sum($values) / $count;
    $sumSq = 0.0;
    foreach ($values as $c) {
        $sumSq += ($c - $mu) ** 2;
    }
    $sigma = $count === 0 ? 0.0 : sqrt($sumSq / $count);
    $flagged = [];
    if ($sigma > 0.0) {
        foreach ($values as $w => $c) {
            $z = ($c - $mu) / $sigma;
            if ($z >= HOTSPOT_Z_THRESHOLD && $c >= HOTSPOT_MIN_INCIDENTS) {
                $flagged[] = ['ward' => (string) $w, 'count' => $c, 'z_exact' => $z];
            }
        }
    }
    usort($flagged, static fn (array $a, array $b): int => $b['z_exact'] <=> $a['z_exact'] ?: strcmp($a['ward'], $b['ward']));
    $a8 = [
        'mu' => round($mu, 4),
        'sigma' => round($sigma, 4),
        'flagged' => array_map(static fn (array $f): array => ['ward' => $f['ward'], 'count' => $f['count'], 'z' => round($f['z_exact'], 4)], $flagged),
    ];

    // A9
    $grid = [];
    foreach ($subUniverse as $s) {
        $grid[$s] = array_fill_keys($months, 0);
    }
    foreach ($pop as $r) {
        if (isset($grid[$r['sub_county']])) {
            $grid[$r['sub_county']][substr($r['occurred_at'], 0, 7)]++;
        }
    }
    $a9 = ['months' => $months, 'rows' => []];
    foreach ($grid as $s => $cells) {
        $a9['rows'][] = ['sub_county' => (string) $s, 'counts' => array_values($cells)];
    }

    // A10
    $statusCounts = array_fill_keys(STATUSES, 0);
    $delays = [];
    $closures = [];
    foreach ($pop as $r) {
        $statusCounts[$r['status']]++;
        $delays[] = toMinutes($r['reported_at']) - toMinutes($r['occurred_at']);
        if ($r['status'] === 'closed') {
            $closures[] = toMinutes($r['closed_at']) - toMinutes($r['reported_at']);
        }
    }
    sort($delays);
    sort($closures);
    $median = nearestRank($delays, 1, 2);
    $p90 = nearestRank($delays, 9, 10);
    $closureMedian = nearestRank($closures, 1, 2);
    $a10 = [
        'status' => $statusCounts,
        'closure_rate_pct' => pct($statusCounts['closed'], $n),
        'report_delay_hours' => [
            'median' => $median === null ? null : round($median / 60, 2),
            'p90' => $p90 === null ? null : round($p90 / 60, 2),
        ],
        'closure_days' => [
            'median' => $closureMedian === null ? null : round($closureMedian / 1440, 2),
        ],
    ];

    return [
        'A1' => ['n' => $n],
        'A2' => $a2, 'A3' => $a3, 'A4' => $a4, 'A5' => $a5,
        'A6' => $a6, 'A7' => $a7, 'A8' => $a8, 'A9' => $a9, 'A10' => $a10,
    ];
}

$args = parseArgs($argv);
$dir = rtrim($args['input'], '/');

$wardToSub = [];
foreach (readCsv(WARDS_CSV, ['sub_county', 'name'], true) as [$sub, $ward]) {
    $wardToSub[$ward] = $sub;
}

$bad = [];
foreach (readCsv("{$dir}/truth/bad_records.csv", ['row', 'rule']) as [$row]) {
    $bad[(int) $row] = true;
}

$raw = readCsv("{$dir}/incidents.csv", INCIDENT_HEADER);
$rows = [];
foreach ($raw as $i => $fields) {
    if (isset($bad[$i + 1])) {
        continue;
    }
    $r = array_combine(INCIDENT_HEADER, $fields);
    // Accepted rows must be internally consistent, or the area universes and timings below would be wrong.
    if (($wardToSub[$r['ward']] ?? null) !== $r['sub_county']) {
        fail('accepted data row '.($i + 1)." has unknown ward or ward outside its sub-county: {$r['ward']}");
    }
    if (! in_array($r['status'], STATUSES, true)) {
        fail('accepted data row '.($i + 1)." has unknown status {$r['status']}");
    }
    if (($r['status'] === 'closed') !== ($r['closed_at'] !== '')) {
        fail('accepted data row '.($i + 1).' has closed_at inconsistent with status');
    }
    toMinutes($r['occurred_at']);
    $rows[] = $r;
}

$filterDoc = json_decode((string) file_get_contents($args['filters']), true, 512, JSON_THROW_ON_ERROR);

$cases = [];
foreach ($filterDoc['cases'] as $case) {
    $filters = $case['filters'];
    if (isset($filters['ward']) && ! isset($wardToSub[$filters['ward']])) {
        fail("case {$case['id']} filters on unknown ward {$filters['ward']}");
    }
    $cases[] = ['id' => $case['id'], 'filters' => $filters, 'metrics' => metrics($rows, $filters, $wardToSub)];
}

$output = [
    'dataset' => [
        'rows_total' => count($raw),
        'rows_excluded_bad' => count($raw) - count($rows),
        'rows_accepted' => count($rows),
    ],
    'cases' => $cases,
];

$json = json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
if (file_put_contents($args['out'], $json."\n") === false) {
    fail("cannot write {$args['out']}");
}
fwrite(STDOUT, "oracle: wrote {$args['out']} (".count($cases).' cases, '.count($rows)." accepted rows)\n");
