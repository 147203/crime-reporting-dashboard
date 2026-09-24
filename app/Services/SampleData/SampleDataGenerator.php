<?php

declare(strict_types=1);

namespace App\Services\SampleData;

use Carbon\CarbonImmutable;
use Random\Engine\Mt19937;
use Random\Randomizer;
use RuntimeException;

/**
 * Deterministic synthetic incident generator (FR-14, docs/04). The same seed, size and end month always produce
 * byte-identical files. It reads the reference lists from database/seeders/data, not from the application database, so
 * the output does not depend on application state.
 *
 * Output directory:
 *   incidents.csv            import format (see ImportService::HEADER)
 *   truth/bad_records.csv    row, rule: rows seeded to break V1 to V6
 *   truth/duplicates.csv     row, original_row, kind (true or near_miss)
 *   truth/hotspots.json      planted ward and category hotspots
 *   truth/trend.json         planted trend
 *   manifest.json            parameters, counts and file hashes
 */
final class SampleDataGenerator
{
    public const HEADER = ['category', 'occurred_at', 'reported_at', 'sub_county', 'ward', 'station', 'location_description', 'description', 'status', 'closed_at'];

    private const LANDMARKS = ['Near the market', 'Opposite the bus stage', 'Behind the petrol station', 'Next to the primary school', 'Near the church', 'Outside the supermarket', 'At the junction', 'Near the health centre', 'Along the main road', 'Near the footbridge', 'By the matatu terminus', 'Outside the chemist'];

    private const DESCRIPTIONS = [
        'Theft' => ['Mobile phone taken from a pocket in a crowd.', 'Handbag snatched and the suspect ran off.', 'Goods taken from a parked vehicle.', 'Items stolen from a shop display.'],
        'Robbery' => ['Victim threatened and cash taken.', 'Two people forced the victim to hand over a phone.', 'Shop attendant robbed at closing time.'],
        'Burglary' => ['House broken into while the occupants were away.', 'Shop padlock cut overnight and stock taken.', 'Office window forced and equipment taken.'],
        'Assault' => ['Fight outside a bar, one person injured.', 'Victim attacked on the way home.', 'Domestic dispute reported by a neighbour.'],
        'Other' => ['Malicious damage to property reported.', 'Suspected fraud reported by a trader.', 'Disturbance reported by residents.'],
    ];

    private Randomizer $random;

    /** @var array<string, list<string>> */
    private array $wardsBySubCounty = [];

    /** @var array<string, string> */
    private array $stationBySubCounty = [];

    /** @var array<string, string> ward name => sub-county name */
    private array $subCountyOfWard = [];

    /**
     * @return array<string, mixed> manifest
     */
    public function generate(int $size, int $seed, string $endMonth, string $directory): array
    {
        if ($size < 100) {
            throw new RuntimeException('Size must be at least 100.');
        }

        $this->random = new Randomizer(new Mt19937($seed));
        $this->loadReferenceData();

        $end = CarbonImmutable::createFromFormat('!Y-m', $endMonth)?->endOfMonth()->startOfMinute() ?? throw new RuntimeException('End month must be YYYY-MM.');
        $months = (int) config('sample_data.months');
        $start = $end->subMonthsNoOverflow($months - 1)->startOfMonth();

        $wards = array_keys($this->subCountyOfWard);
        $wardWeights = $this->wardWeights($wards);
        [$plantedWards, $plantedCategoryWards] = $this->plantHotspots($wards, $wardWeights);

        $rows = [];
        for ($i = 0; $i < $size; $i++) {
            $rows[] = ['row' => $this->record($start, $end, $months, $wardWeights, $plantedCategoryWards), 'kind' => 'valid', 'rule' => null, 'original' => null];
        }

        $bad = $this->badRows($rows, $size, $end);
        [$duplicates, $nearMisses] = $this->duplicateRows($rows, $size, $end);
        $all = [...$rows, ...$bad, ...$duplicates, ...$nearMisses];

        // Shuffle deterministically; remember each original's position so duplicates can point at final row numbers.
        foreach ($all as $index => &$entry) {
            $entry['id'] = $index;
        }
        unset($entry);
        $all = $this->random->shuffleArray($all);
        $position = [];
        foreach ($all as $rowNumber => $entry) {
            $position[$entry['id']] = $rowNumber + 1;
        }

        return $this->write($directory, $all, $position, compact('size', 'seed', 'endMonth', 'start', 'end', 'plantedWards', 'plantedCategoryWards'));
    }

    private function loadReferenceData(): void
    {
        $read = function (string $file): array {
            $lines = array_values(array_filter(file(database_path("seeders/data/{$file}"), FILE_IGNORE_NEW_LINES) ?: [], fn (string $l): bool => $l !== '' && ! str_starts_with($l, '#')));
            array_shift($lines);

            return array_map(fn (string $l): array => str_getcsv($l, escape: ''), $lines);
        };

        $this->wardsBySubCounty = [];
        $this->subCountyOfWard = [];
        foreach ($read('wards.csv') as [$subCounty, $ward]) {
            $this->wardsBySubCounty[(string) $subCounty][] = (string) $ward;
            $this->subCountyOfWard[(string) $ward] = (string) $subCounty;
        }
        foreach ($read('stations.csv') as [$subCounty, $station]) {
            $this->stationBySubCounty[(string) $subCounty] = (string) $station;
        }
    }

    /**
     * @param  list<string>  $wards
     * @return array<string, float>
     */
    private function wardWeights(array $wards): array
    {
        [$low, $high] = config('sample_data.ward_weight_range');
        $weights = [];
        foreach ($wards as $ward) {
            $weights[$ward] = round($low + $this->random->nextFloat() * ($high - $low), 6);
        }

        return $weights;
    }

    /**
     * @param  list<string>  $wards
     * @param  array<string, float>  $weights  multiplied in place for planted wards
     * @return array{0: list<string>, 1: list<string>}
     */
    private function plantHotspots(array $wards, array &$weights): array
    {
        $wardCount = max(1, (int) config('sample_data.planted_ward_hotspots.count'));
        $categoryCount = max(1, (int) config('sample_data.planted_category_hotspots.count'));
        $choices = array_flip($wards);
        if ($choices === [] || count($choices) < $wardCount + $categoryCount) {
            throw new RuntimeException('Not enough wards in the reference data to plant hotspots.');
        }
        $picked = array_map('strval', $this->random->pickArrayKeys($choices, $wardCount + $categoryCount));
        $planted = array_slice($picked, 0, $wardCount);
        $categoryPlanted = array_slice($picked, $wardCount);

        foreach ($planted as $name) {
            $weights[$name] *= (float) config('sample_data.planted_ward_hotspots.multiplier');
        }

        return [$planted, $categoryPlanted];
    }

    /**
     * @param  array<string, float>  $wardWeights
     * @param  list<string>  $plantedCategoryWards
     * @return array<string, string>
     */
    private function record(CarbonImmutable $start, CarbonImmutable $end, int $months, array $wardWeights, array $plantedCategoryWards): array
    {
        $category = (string) $this->weightedPick(config('sample_data.category_mix'));

        $trend = config('sample_data.planted_trend');
        $amplitude = (float) config('sample_data.seasonality_amplitude');
        $monthWeights = [];
        for ($m = 0; $m < $months; $m++) {
            $weight = 1 + $amplitude * sin(2 * M_PI * $start->addMonthsNoOverflow($m)->month / 12);
            if ($category === $trend['category']) {
                $weight *= 1 + $trend['growth'] * ($m / max(1, $months - 1));
            }
            $monthWeights[$m] = $weight;
        }
        $month = $start->addMonthsNoOverflow((int) $this->weightedPick($monthWeights));

        $weights = $wardWeights;
        $categoryPlant = config('sample_data.planted_category_hotspots');
        if ($category === $categoryPlant['category']) {
            foreach ($plantedCategoryWards as $name) {
                $weights[$name] *= $categoryPlant['multiplier'];
            }
        }
        $ward = (string) $this->weightedPick($weights);
        $subCounty = $this->subCountyOfWard[$ward];

        $weekdayWeights = config('sample_data.weekday_weights')[$category] ?? config('sample_data.weekday_weights')['default'];
        $dayWeights = [];
        for ($d = 1; $d <= $month->daysInMonth; $d++) {
            $dayWeights[$d] = $weekdayWeights[$month->setDay($d)->dayOfWeekIso];
        }
        $day = (int) $this->weightedPick($dayWeights);

        $bands = config('sample_data.band_weights')[$category] ?? config('sample_data.band_weights')['default'];
        $band = (int) $this->weightedPick($bands);
        $occurred = $month->setDay($day)->setTime($band * 6 + $this->random->getInt(0, 5), $this->random->getInt(0, 59));
        if ($occurred->greaterThan($end)) {
            $occurred = $end->subHours($this->random->getInt(1, 48));
        }

        $delayMinutes = (int) floor($this->random->nextFloat() ** 2 * 72 * 60);
        $reported = $occurred->addMinutes($delayMinutes);
        if ($reported->greaterThan($end)) {
            $reported = $end;
        }

        $ageDays = $occurred->diffInDays($end);
        $roll = $this->random->nextFloat();
        $status = $ageDays > 60
            ? ($roll < 0.55 ? 'closed' : ($roll < 0.80 ? 'under_investigation' : 'reported'))
            : ($roll < 0.10 ? 'closed' : ($roll < 0.40 ? 'under_investigation' : 'reported'));
        $closed = '';
        if ($status === 'closed') {
            $closedAt = $reported->addMinutes($this->random->getInt(60, 60 * 24 * 60));
            $closed = ($closedAt->greaterThan($end) ? $end : $closedAt)->format('Y-m-d H:i');
        }

        return [
            'category' => $category,
            'occurred_at' => $occurred->format('Y-m-d H:i'),
            'reported_at' => $reported->format('Y-m-d H:i'),
            'sub_county' => $subCounty,
            'ward' => $ward,
            'station' => $this->stationBySubCounty[$subCounty],
            'location_description' => self::LANDMARKS[$this->random->getInt(0, count(self::LANDMARKS) - 1)].' '.$this->random->getInt(1, 400),
            'description' => self::DESCRIPTIONS[$category][$this->random->getInt(0, count(self::DESCRIPTIONS[$category]) - 1)],
            'status' => $status,
            'closed_at' => $closed,
        ];
    }

    /**
     * @param  list<array{row: array<string, string>, kind: string, rule: ?string, original: ?int}>  $rows
     * @return list<array{row: array<string, string>, kind: string, rule: ?string, original: ?int}>
     */
    private function badRows(array $rows, int $size, CarbonImmutable $end): array
    {
        $count = max(5, (int) round($size * (float) config('sample_data.bad_share_per_rule')));
        $bad = [];
        $subCounties = array_keys($this->wardsBySubCounty);

        foreach (['V1', 'V2', 'V3', 'V4', 'V5', 'V6'] as $rule) {
            for ($i = 0; $i < $count; $i++) {
                $row = $rows[$this->random->getInt(0, $size - 1)]['row'];
                $alternate = $i % 2 === 0;

                switch ($rule) {
                    case 'V1':
                        $row['category'] = $alternate ? 'Unknown category' : '';
                        break;
                    case 'V2':
                        $occurred = $alternate ? $end->addDays(400 + $i) : $end->subYears(12)->subDays($i);
                        $row['occurred_at'] = $occurred->format('Y-m-d H:i');
                        $row['reported_at'] = $occurred->addHour()->format('Y-m-d H:i');
                        $row['status'] = 'reported';
                        $row['closed_at'] = '';
                        break;
                    case 'V3':
                        $row['reported_at'] = CarbonImmutable::parse($row['occurred_at'])->subHours(3 + $i % 20)->format('Y-m-d H:i');
                        break;
                    case 'V4':
                        $other = $subCounties[($this->random->getInt(1, count($subCounties) - 1) + array_search($row['sub_county'], $subCounties, true)) % count($subCounties)];
                        $row['ward'] = $this->wardsBySubCounty[$other][0];
                        break;
                    case 'V5':
                        if ($alternate) {
                            $row['location_description'] = str_repeat('Near the long road ', 9);
                        } else {
                            $row['description'] = str_repeat('Long description text. ', 48);
                        }
                        break;
                    case 'V6':
                        if ($alternate) {
                            $row['description'] .= ' Owner can be reached on 07'.$this->random->getInt(10, 99).' '.$this->random->getInt(100, 999).' '.$this->random->getInt(100, 999).'.';
                        } else {
                            $row['location_description'] = 'House of ID '.$this->random->getInt(20000000, 39999999);
                        }
                        break;
                }

                $bad[] = ['row' => $row, 'kind' => 'bad', 'rule' => $rule, 'original' => null];
            }
        }

        return $bad;
    }

    /**
     * @param  list<array{row: array<string, string>, kind: string, rule: ?string, original: ?int}>  $rows
     * @return array{0: list<array{row: array<string, string>, kind: string, rule: ?string, original: ?int}>, 1: list<array{row: array<string, string>, kind: string, rule: ?string, original: ?int}>}
     */
    private function duplicateRows(array $rows, int $size, CarbonImmutable $end): array
    {
        $window = (int) config('analytics.duplicates.window_minutes');
        $duplicates = [];
        $nearMisses = [];
        $used = [];

        $pickOriginal = function () use ($size, &$used): int {
            do {
                $index = $this->random->getInt(0, $size - 1);
            } while (isset($used[$index]));
            $used[$index] = true;

            return $index;
        };

        $duplicateCount = max(10, (int) round($size * (float) config('sample_data.duplicate_share')));
        for ($i = 0; $i < $duplicateCount; $i++) {
            $index = $pickOriginal();
            $row = $this->shifted($rows[$index]['row'], $this->random->getInt(5, (int) ($window * 0.8)), $end);
            $row['location_description'] = $this->retyped($row['location_description']);
            $duplicates[] = ['row' => $row, 'kind' => 'duplicate', 'rule' => null, 'original' => $index];
        }

        // Two kinds of near miss, which must NOT be flagged: outside the time window, and same place and time but a
        // different landmark.
        $nearMissCount = max(10, (int) round($size * (float) config('sample_data.near_miss_share')));
        for ($i = 0; $i < $nearMissCount; $i++) {
            $index = $pickOriginal();
            $original = $rows[$index]['row'];
            if ($i % 2 === 0) {
                $row = $this->shifted($original, $window + 30 + $this->random->getInt(0, 600), $end);
            } else {
                $row = $this->shifted($original, $this->random->getInt(5, (int) ($window * 0.8)), $end);
                $row['location_description'] = $this->differentLandmark($original['location_description']);
            }
            $nearMisses[] = ['row' => $row, 'kind' => 'near_miss', 'rule' => null, 'original' => $index];
        }

        return [$duplicates, $nearMisses];
    }

    /**
     * Moves the time by the given minutes, backwards when forwards would pass the end of the period, so every copy
     * stays inside the generated months.
     *
     * @param  array<string, string>  $row
     * @return array<string, string>
     */
    private function shifted(array $row, int $minutes, CarbonImmutable $end): array
    {
        $occurred = CarbonImmutable::parse($row['occurred_at'])->addMinutes($minutes);
        if ($occurred->greaterThan($end)) {
            $occurred = CarbonImmutable::parse($row['occurred_at'])->subMinutes($minutes);
        }
        $reported = $occurred->addMinutes($this->random->getInt(10, 90));

        $row['occurred_at'] = $occurred->format('Y-m-d H:i');
        $row['reported_at'] = ($reported->greaterThan($end) ? $end : $reported)->format('Y-m-d H:i');
        $row['status'] = 'reported';
        $row['closed_at'] = '';

        return $row;
    }

    /**
     * How a second person might type the same place: a typo, swapped letters, a different case, an extra or a dropped
     * word. Some of these fall below the similarity threshold on purpose, so recall is measured, not assumed.
     */
    private function retyped(string $text): string
    {
        $position = $this->random->getInt(1, max(1, strlen($text) - 2));

        return match ($this->random->getInt(0, 5)) {
            0 => strtoupper($text),
            1 => substr($text, 0, $position).substr($text, $position + 1),
            2 => substr($text, 0, $position).$text[$position + 1].$text[$position].substr($text, $position + 2),
            3 => $text.' gate',
            4 => str_replace('the ', '', $text),
            default => $text.'.',
        };
    }

    private function differentLandmark(string $text): string
    {
        $current = array_search(trim((string) preg_replace('/\s\d+$/', '', $text)), self::LANDMARKS, true);
        $next = self::LANDMARKS[((int) $current + 1 + $this->random->getInt(0, count(self::LANDMARKS) - 2)) % count(self::LANDMARKS)];

        return $next.' '.$this->random->getInt(1, 400);
    }

    /**
     * @param  array<int|string, float|int>  $weights
     */
    private function weightedPick(array $weights): int|string
    {
        $total = array_sum($weights);
        $target = $this->random->nextFloat() * $total;
        $running = 0.0;
        foreach ($weights as $key => $weight) {
            $running += $weight;
            if ($target < $running) {
                return $key;
            }
        }

        return array_key_last($weights) ?? throw new RuntimeException('Cannot pick from an empty weight list.');
    }

    /**
     * @param  list<array{row: array<string, string>, kind: string, rule: ?string, original: ?int, id: int}>  $all
     * @param  array<int, int>  $position
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    private function write(string $directory, array $all, array $position, array $parameters): array
    {
        if (! is_dir($directory.'/truth') && ! mkdir($directory.'/truth', 0775, true) && ! is_dir($directory.'/truth')) {
            throw new RuntimeException("Cannot create {$directory}/truth");
        }

        $csv = $this->csvLine(self::HEADER);
        $bad = $this->csvLine(['row', 'rule']);
        $dups = $this->csvLine(['row', 'original_row', 'kind']);
        $counts = ['valid' => 0, 'bad' => 0, 'duplicate' => 0, 'near_miss' => 0];

        foreach ($all as $rowNumber => $entry) {
            $csv .= $this->csvLine(array_map(fn (string $column): string => $entry['row'][$column], self::HEADER));
            $counts[$entry['kind']]++;
            if ($entry['kind'] === 'bad') {
                $bad .= $this->csvLine([(string) ($rowNumber + 1), (string) $entry['rule']]);
            }
            if ($entry['original'] !== null) {
                $dups .= $this->csvLine([(string) ($rowNumber + 1), (string) $position[$entry['original']], $entry['kind'] === 'duplicate' ? 'true' : 'near_miss']);
            }
        }

        $hotspots = json_encode([
            'grain' => 'ward',
            'ward_hotspots' => array_values($parameters['plantedWards']),
            'ward_multiplier' => config('sample_data.planted_ward_hotspots.multiplier'),
            'category_hotspots' => ['category' => config('sample_data.planted_category_hotspots.category'), 'wards' => array_values($parameters['plantedCategoryWards']), 'multiplier' => config('sample_data.planted_category_hotspots.multiplier')],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
        $trend = json_encode(config('sample_data.planted_trend'), JSON_PRETTY_PRINT)."\n";

        $files = ['incidents.csv' => $csv, 'truth/bad_records.csv' => $bad, 'truth/duplicates.csv' => $dups, 'truth/hotspots.json' => $hotspots, 'truth/trend.json' => $trend];
        $hashes = [];
        foreach ($files as $name => $content) {
            file_put_contents("{$directory}/{$name}", $content);
            $hashes[$name] = hash('sha256', $content);
        }

        $manifest = [
            'generator' => 'SampleDataGenerator',
            'seed' => $parameters['seed'],
            'size' => $parameters['size'],
            'end_month' => $parameters['endMonth'],
            'period' => ['from' => $parameters['start']->format('Y-m-d H:i'), 'to' => $parameters['end']->format('Y-m-d H:i')],
            'rows' => array_sum($counts),
            'counts' => $counts,
            'parameters' => config('sample_data'),
            'sha256' => $hashes,
        ];
        file_put_contents("{$directory}/manifest.json", json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        return $manifest;
    }

    /**
     * @param  list<string>  $fields
     */
    private function csvLine(array $fields): string
    {
        $handle = fopen('php://memory', 'w+');
        if ($handle === false) {
            throw new RuntimeException('Cannot open memory stream.');
        }
        fputcsv($handle, $fields, ',', '"', '', "\n");
        rewind($handle);
        $line = (string) stream_get_contents($handle);
        fclose($handle);

        return $line;
    }
}
