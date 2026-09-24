<?php

declare(strict_types=1);

namespace Tests\Feature\Schema;

use App\Models\Incident;
use App\Models\Ward;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SchemaTest extends TestCase
{
    use RefreshDatabase;

    /** TC-FT-10: D-02, no spatial data anywhere in the schema. */
    #[Test]
    public function no_table_has_a_coordinate_or_spatial_column(): void
    {
        $offending = DB::table('information_schema.columns')
            ->where('table_schema', DB::connection()->getDatabaseName())
            ->where(function ($query): void {
                $query->where('column_name', 'regexp', '(^|_)(lat|latitude|lon|lng|longitude|geo|geom|coord|coordinates|gps)(_|$)')
                    ->orWhereIn('data_type', ['point', 'geometry', 'polygon', 'linestring', 'multipoint', 'multipolygon', 'geometrycollection']);
            })
            ->get(['table_name', 'column_name'])
            ->map(fn (object $column): string => $column->table_name.'.'.$column->column_name)
            ->all();

        $this->assertSame([], $offending);
    }

    /** TC-FT-16: D-08, no personal identifiers of victims, suspects or witnesses. */
    #[Test]
    public function the_incident_schema_has_no_personal_identifier_columns(): void
    {
        $offending = collect(Schema::getColumnListing('incidents'))
            ->filter(fn (string $column): bool => (bool) preg_match('/victim|suspect|witness|complainant|phone|mobile|national_id|id_number|id_no|passport|dob|birth|gender|first_name|last_name|full_name/', $column))
            ->values()
            ->all();

        $this->assertSame([], $offending);
    }

    /** TC-FT-17: V4 is also enforced by the database (composite key). */
    #[Test]
    public function the_database_rejects_a_ward_from_another_sub_county(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $ward = Ward::query()->firstOrFail();
        $otherSubCounty = DB::table('sub_counties')->where('id', '!=', $ward->sub_county_id)->value('id');

        $this->expectException(QueryException::class);

        DB::table('incidents')->insert([
            'category_id' => DB::table('crime_categories')->value('id'),
            'occurred_at' => '2026-01-01 10:00:00',
            'reported_at' => '2026-01-01 11:00:00',
            'sub_county_id' => $otherSubCounty,
            'ward_id' => $ward->id,
            'station_id' => DB::table('stations')->value('id'),
        ]);
    }

    /** TC-FT-18: status values and the closed_at rule are enforced by CHECK constraints. */
    #[Test]
    public function the_database_rejects_an_unknown_status_and_a_closed_record_without_closed_at(): void
    {
        $incident = Incident::factory()->create();

        foreach ([['status' => 'archived'], ['status' => 'closed', 'closed_at' => null], ['duplicate_status' => 'maybe']] as $change) {
            try {
                DB::table('incidents')->where('id', $incident->id)->update($change);
                $this->fail('The database accepted '.json_encode($change));
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /** TC-FT-11: indexes from docs/03 section 3 exist under their explicit names. */
    #[Test]
    public function the_documented_indexes_exist(): void
    {
        $expected = [
            'incidents' => [
                'incidents_occurred_at_index',
                'incidents_category_occurred_index',
                'incidents_ward_occurred_index',
                'incidents_sub_county_occurred_index',
                'incidents_station_occurred_index',
                'incidents_status_index',
                'incidents_duplicate_status_index',
                'incidents_reference_no_unique',
                'incidents_analytics_covering_index',
            ],
            'audit_logs' => ['audit_logs_created_at_index', 'audit_logs_user_created_index'],
            'wards' => ['wards_sub_county_id_name_unique'],
        ];

        foreach ($expected as $table => $names) {
            $present = array_column(Schema::getIndexes($table), 'name');

            foreach ($names as $name) {
                $this->assertContains($name, $present, "Missing index {$name} on {$table}.");
            }
        }
    }

    /** TC-FT-12 */
    #[Test]
    public function reference_data_loads_the_county_hierarchy_and_is_idempotent(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(ReferenceDataSeeder::class);

        $this->assertSame(17, DB::table('sub_counties')->count());
        $this->assertSame(85, DB::table('wards')->count());
        $this->assertSame(17, DB::table('stations')->count());
        $this->assertSame(5, DB::table('crime_categories')->count());
        $this->assertSame(0, DB::table('wards')->whereNotIn('sub_county_id', DB::table('sub_counties')->select('id'))->count());

        $wardsPerSubCounty = DB::table('wards')->join('sub_counties', 'sub_counties.id', '=', 'wards.sub_county_id')
            ->groupBy('sub_counties.name')->orderBy('sub_counties.name')->pluck(DB::raw('count(*)'), 'sub_counties.name')
            ->map(fn (mixed $count): int => (int) $count)->all();
        $this->assertSame(6, $wardsPerSubCounty['Starehe']);
        $this->assertSame(6, $wardsPerSubCounty['Mathare']);
        $this->assertSame(4, $wardsPerSubCounty['Makadara']);
        $this->assertSame(4, $wardsPerSubCounty['Embakasi West']);
        $this->assertSame(5, $wardsPerSubCounty['Westlands']);
    }
}
