<?php

declare(strict_types=1);

namespace Tests\Feature\Quality;

use App\Models\Incident;
use App\Services\Import\ImportService;
use App\Services\SampleData\SampleDataGenerator;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * NFR-05 data-quality accuracy against the generator's ground truth (docs/04 section 4). Numbers are printed to the
 * test output so they can be copied into the test log.
 */
final class DataQualityAccuracyTest extends TestCase
{
    use RefreshDatabase;

    private const PINNED_SHA256 = '467810bbdbe3ec061d39f2369213761a75a014c037e0a962499c2b010729300c';

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-09-02 08:00:00');
        $this->seed(ReferenceDataSeeder::class);
        $this->directory = storage_path('framework/testing/sample-'.getmypid());
        File::deleteDirectory($this->directory);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    /**
     * @return list<array<string, string>>
     */
    private function readCsv(string $file): array
    {
        $lines = file("{$this->directory}/{$file}", FILE_IGNORE_NEW_LINES) ?: [];
        $header = array_map('strval', str_getcsv((string) array_shift($lines), escape: ''));

        return array_map(fn (string $line): array => array_combine($header, array_map('strval', str_getcsv($line, escape: ''))), $lines);
    }

    /** TC-AC-02 (rejection recall per rule, false rejections) and TC-AC-03 (duplicate precision and recall). */
    #[Test]
    public function seeded_errors_are_rejected_and_seeded_duplicates_are_flagged(): void
    {
        $manifest = app(SampleDataGenerator::class)->generate(1000, 20260924, '2026-08', $this->directory);
        $result = app(ImportService::class)->importFile("{$this->directory}/incidents.csv", null);

        $rejectedRows = array_unique(array_column($result['errors'], 'row'));
        $bad = $this->readCsv('truth/bad_records.csv');
        $badRows = array_map('intval', array_column($bad, 'row'));

        $rulesByRow = [];
        foreach ($result['errors'] as $error) {
            $rulesByRow[$error['row']][] = $error['rule'];
        }

        // A seeded row counts as caught only when the rule it was built to break is among the rules reported for it.
        $recall = [];
        foreach (array_unique(array_column($bad, 'rule')) as $rule) {
            $seeded = array_map('intval', array_column(array_filter($bad, fn (array $r): bool => $r['rule'] === $rule), 'row'));
            $caught = array_filter($seeded, fn (int $row): bool => in_array($rule, $rulesByRow[$row] ?? [], true));
            $recall[$rule] = count($caught) / count($seeded);
        }
        $falseRejections = array_diff($rejectedRows, $badRows);

        // Accepted rows were inserted in file order, so the n-th incident id is the n-th accepted row.
        $acceptedRows = array_values(array_diff(range(1, $manifest['rows']), $rejectedRows));
        $rowOfId = array_combine(Incident::query()->orderBy('id')->pluck('id')->all(), $acceptedRows);
        $flaggedPairs = Incident::query()->whereNotNull('duplicate_of_id')->get(['id', 'duplicate_of_id'])
            ->map(fn (Incident $i): string => $rowOfId[$i->id].'>'.$rowOfId[$i->duplicate_of_id])->all();

        $truePairs = [];
        foreach ($this->readCsv('truth/duplicates.csv') as $pair) {
            if ($pair['kind'] === 'true') {
                // Either member may be inserted first, so the pair is matched in both directions.
                $truePairs[] = $pair['row'].'>'.$pair['original_row'];
                $truePairs[] = $pair['original_row'].'>'.$pair['row'];
            }
        }
        $trueCount = count($truePairs) / 2;
        $hits = count(array_intersect($flaggedPairs, $truePairs));
        $precision = $flaggedPairs === [] ? 0.0 : $hits / count($flaggedPairs);
        $duplicateRecall = $hits / $trueCount;

        fwrite(STDERR, sprintf(
            "\n[TC-AC-02] rows=%d accepted=%d rejected=%d recall=%s false_rejections=%d\n[TC-AC-03] flagged=%d true_pairs=%d hits=%d precision=%.3f recall=%.3f\n",
            $manifest['rows'], $result['accepted'], $result['rejected'], json_encode(array_map(fn (float $v): float => round($v, 3), $recall)), count($falseRejections),
            count($flaggedPairs), $trueCount, $hits, $precision, $duplicateRecall,
        ));

        foreach ($recall as $rule => $value) {
            $this->assertGreaterThanOrEqual(0.95, $value, "Rejection recall for {$rule}");
        }
        $this->assertSame([], array_values($falseRejections), 'Valid rows were rejected.');
        $this->assertGreaterThanOrEqual(0.90, $precision, 'Duplicate precision');
        $this->assertGreaterThanOrEqual(0.90, $duplicateRecall, 'Duplicate recall');
    }

    /** TC-AC-04: FR-14 determinism. */
    #[Test]
    public function the_same_seed_produces_identical_files(): void
    {
        $first = app(SampleDataGenerator::class)->generate(300, 7, '2026-08', $this->directory.'/a');
        $second = app(SampleDataGenerator::class)->generate(300, 7, '2026-08', $this->directory.'/b');
        $other = app(SampleDataGenerator::class)->generate(300, 8, '2026-08', $this->directory.'/c');

        $this->assertSame($first['sha256'], $second['sha256']);
        // Pinned across processes and machines: a change here means the generator's output changed (re-run the oracle).
        $this->assertSame(self::PINNED_SHA256, $first['sha256']['incidents.csv']);
        $this->assertNotSame($first['sha256']['incidents.csv'], $other['sha256']['incidents.csv']);
    }
}
