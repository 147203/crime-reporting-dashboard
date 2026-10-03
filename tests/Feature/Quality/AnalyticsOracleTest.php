<?php

declare(strict_types=1);

namespace Tests\Feature\Quality;

use App\Models\CrimeCategory;
use App\Models\Station;
use App\Models\SubCounty;
use App\Models\User;
use App\Models\Ward;
use App\Services\Analytics\Analytics;
use App\Services\Import\ImportService;
use App\Services\SampleData\SampleDataGenerator;
use App\Support\Filters;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * TC-AC-01 (NFR-05, FR-09): the application's A1 to A10 equal the independent oracle (scripts/oracle.php) on the
 * seeded 1,000-record dataset for every fixed filter case. Expected values are never taken from the application.
 */
final class AnalyticsOracleTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-09-02 08:00:00');
        $this->seed(ReferenceDataSeeder::class);
        $this->directory = storage_path('framework/testing/oracle-'.getmypid());
        File::deleteDirectory($this->directory);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    #[Test]
    public function every_metric_matches_the_oracle_for_every_filter_case(): void
    {
        $spec = json_decode((string) file_get_contents(base_path('tests/Fixtures/oracle/filters.json')), true, flags: JSON_THROW_ON_ERROR);
        $expected = json_decode((string) file_get_contents(base_path('tests/Fixtures/oracle/expected_metrics_1000.json')), true, flags: JSON_THROW_ON_ERROR);
        $dataset = $spec['dataset'];

        app(SampleDataGenerator::class)->generate($dataset['size'], $dataset['seed'], $dataset['end_month'], $this->directory);
        $loaded = app(ImportService::class)->importFile($this->directory.'/incidents.csv', null);
        $this->assertSame($expected['dataset']['rows_accepted'], $loaded['accepted'], 'Accepted rows differ from the oracle dataset.');

        $admin = User::factory()->admin()->create();
        $compared = 0;

        foreach ($expected['cases'] as $case) {
            $actual = (new Analytics($admin, $this->filters($case['filters'])))->all();
            unset($actual['A8']['threshold'], $actual['A8']['min_incidents']);
            // Row ids are database keys for links; the oracle works from names only.
            $withoutId = fn (array $rows): array => array_map(fn (array $row): array => array_diff_key($row, ['id' => true]), $rows);
            $actual['A7']['wards'] = $withoutId($actual['A7']['wards']);
            $actual['A7']['sub_counties'] = $withoutId($actual['A7']['sub_counties']);
            $actual['A8']['flagged'] = $withoutId($actual['A8']['flagged']);

            foreach ($case['metrics'] as $metric => $values) {
                $compared += $this->assertMatches($values, $actual[$metric], "{$case['id']}.{$metric}");
            }
        }

        fwrite(STDERR, "\n[TC-AC-01] cases=".count($expected['cases'])." values_compared={$compared} mismatches=0\n");
    }

    /**
     * @param  array<string, string>  $filters
     */
    private function filters(array $filters): Filters
    {
        return Filters::fromValidated([
            'from' => $filters['from'],
            'to' => $filters['to'],
            'category_id' => isset($filters['category']) ? CrimeCategory::query()->where('name', $filters['category'])->value('id') : null,
            'sub_county_id' => isset($filters['sub_county']) ? SubCounty::query()->where('name', $filters['sub_county'])->value('id') : null,
            'ward_id' => isset($filters['ward']) ? Ward::query()->where('name', $filters['ward'])->value('id') : null,
            'station_id' => isset($filters['station']) ? Station::query()->where('name', $filters['station'])->value('id') : null,
            'status' => $filters['status'] ?? null,
        ]);
    }

    /**
     * Exact comparison; floats (already rounded by both sides) may differ only by representation (1e-9).
     */
    private function assertMatches(mixed $expected, mixed $actual, string $path): int
    {
        if (is_array($expected)) {
            $this->assertIsArray($actual, $path);
            $this->assertSame(array_keys($expected), array_keys($actual), "Keys differ at {$path}");
            $count = 0;
            foreach ($expected as $key => $value) {
                $count += $this->assertMatches($value, $actual[$key], "{$path}.{$key}");
            }

            return $count;
        }

        if ($expected === null || $actual === null) {
            $this->assertSame($expected, $actual, $path);

            return 1;
        }

        if (is_float($expected) || is_float($actual)) {
            $this->assertIsNumeric($actual, $path);
            $this->assertEqualsWithDelta((float) $expected, (float) $actual, 1e-9, $path);

            return 1;
        }

        $this->assertSame($expected, $actual, $path);

        return 1;
    }
}
