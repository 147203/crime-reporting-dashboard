<?php

declare(strict_types=1);

namespace Tests\Feature\Incidents;

use App\Enums\AgeBand;
use App\Enums\InjuryLevel;
use App\Enums\OffenderRelationship;
use App\Enums\VictimSex;
use App\Enums\VictimType;
use App\Models\AuditLog;
use App\Models\CrimeCategory;
use App\Models\ImportBatch;
use App\Models\Incident;
use App\Models\Station;
use App\Models\User;
use App\Models\Ward;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * FR-05, V9, D-53: the victim profile is recorded, normalised, shown, filtered, imported and exported, and never holds
 * an identifier.
 */
final class VictimProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-09-02 08:00:00');
        $this->seed(ReferenceDataSeeder::class);
        Storage::fake('local');
        $this->admin = User::factory()->admin()->create();
    }

    /**
     * @param  array<string, mixed>  $victim
     * @return array<string, mixed>
     */
    private function payload(array $victim = []): array
    {
        $ward = Ward::query()->where('name', 'Makina')->sole();

        return [
            'category_id' => CrimeCategory::query()->where('name', 'Assault')->value('id'),
            'occurred_at' => '2026-09-01T21:00',
            'reported_at' => '2026-09-01T22:00',
            'sub_county_id' => $ward->sub_county_id,
            'ward_id' => $ward->id,
            'station_id' => Station::query()->where('sub_county_id', $ward->sub_county_id)->value('id'),
            'location_description' => 'Near the market 5',
            'description' => 'Victim attacked on the way home.',
            ...$victim,
        ];
    }

    /** TC-FT-68: an individual victim is stored with an age band set from the exact age, and shown on the record. */
    #[Test]
    public function an_individual_victim_is_recorded_and_the_band_follows_the_age(): void
    {
        $this->actingAs($this->admin)->post(route('incidents.store'), $this->payload([
            'victim_type' => 'individual', 'victim_count' => '2', 'victim_sex' => 'female', 'victim_age' => '29',
            'victim_age_band' => '65_plus', 'victim_injury' => 'serious', 'offender_relationship' => 'family_or_partner',
        ]))->assertSessionHasNoErrors();

        $incident = Incident::query()->sole();
        $this->assertSame(VictimType::Individual, $incident->victim_type);
        $this->assertSame(2, $incident->victim_count);
        $this->assertSame(VictimSex::Female, $incident->victim_sex);
        $this->assertSame(29, $incident->victim_age);
        $this->assertSame(AgeBand::From25To34, $incident->victim_age_band);
        $this->assertSame(InjuryLevel::Serious, $incident->victim_injury);
        $this->assertSame(OffenderRelationship::FamilyOrPartner, $incident->offender_relationship);

        $this->get(route('incidents.show', $incident))->assertOk()
            ->assertSeeInOrder(['Victim', 'Individual person', '2', 'Serious injury', 'Female', '29 years (25 to 34)', 'Family member or partner']);
        $this->get(route('incidents.edit', $incident))->assertOk()->assertSee('value="29"', false)->assertSee('data-victim-form', false);
    }

    /** TC-FT-69: a business victim keeps no person details, and an unrecorded profile defaults to "not recorded". */
    #[Test]
    public function person_details_are_cleared_for_a_business_and_blank_means_not_recorded(): void
    {
        $this->actingAs($this->admin)->post(route('incidents.store'), $this->payload([
            'victim_type' => 'business', 'victim_sex' => 'male', 'victim_age' => '40', 'offender_relationship' => 'stranger', 'victim_injury' => 'none',
        ]))->assertSessionHasNoErrors();
        $this->post(route('incidents.store'), $this->payload(['description' => 'Second record.', 'location_description' => 'Opposite the stage 9']))->assertSessionHasNoErrors();

        [$business, $blank] = Incident::query()->orderBy('id')->get()->all();
        $this->assertSame([VictimSex::NotRecorded, null, AgeBand::NotRecorded, OffenderRelationship::NotKnown], [$business->victim_sex, $business->victim_age, $business->victim_age_band, $business->offender_relationship]);
        $this->assertSame([VictimType::NotRecorded, null, VictimSex::NotRecorded, AgeBand::NotRecorded, InjuryLevel::NotRecorded], [$blank->victim_type, $blank->victim_count, $blank->victim_sex, $blank->victim_age_band, $blank->victim_injury]);
    }

    /** TC-SE-25: V9 rejects values outside the lists and ranges, so no free text or identifier can enter the profile. */
    #[Test]
    public function values_outside_the_lists_and_ranges_are_rejected(): void
    {
        $this->actingAs($this->admin);
        foreach ([
            ['victim_sex' => 'Jane Wanjiru'], ['victim_type' => 'person'], ['victim_injury' => 'broken arm'],
            ['offender_relationship' => 'husband John'], ['victim_age_band' => 'adult'], ['victim_age' => '121'],
            ['victim_age' => '-1'], ['victim_age' => 'thirty'], ['victim_count' => '51'], ['victim_count' => '0'],
            ['victim_sex' => ['female']], ['victim_type' => ['individual']],
        ] as $bad) {
            $this->post(route('incidents.store'), $this->payload(['victim_type' => 'individual', ...$bad]))->assertSessionHasErrors(array_key_first($bad));
        }
        $this->assertSame(0, Incident::query()->count());
    }

    /** TC-FT-70: editing changes the victim profile and the change is audited by field name only. */
    #[Test]
    public function the_victim_profile_can_be_edited(): void
    {
        $this->actingAs($this->admin)->post(route('incidents.store'), $this->payload(['victim_type' => 'individual', 'victim_sex' => 'male']));
        $incident = Incident::query()->sole();

        $this->put(route('incidents.update', $incident), $this->payload(['victim_type' => 'individual', 'victim_sex' => 'female', 'victim_age_band' => '18_24', 'victim_injury' => 'minor']))->assertSessionHasNoErrors();

        $incident->refresh();
        $this->assertSame([VictimSex::Female, AgeBand::From18To24, InjuryLevel::Minor], [$incident->victim_sex, $incident->victim_age_band, $incident->victim_injury]);
        $entry = AuditLog::query()->where('action', 'incident.updated')->sole();
        $meta = $entry->getAttribute('meta');
        $this->assertIsArray($meta);
        $this->assertContains('victim_sex', $meta['fields']);
        $this->assertStringNotContainsString('female', (string) json_encode($meta));
    }

    /** TC-FT-71: the incident list filters on every victim field. */
    #[Test]
    public function the_list_filters_on_victim_fields(): void
    {
        $this->actingAs($this->admin);
        $this->post(route('incidents.store'), $this->payload(['victim_type' => 'individual', 'victim_sex' => 'female', 'victim_age' => '70', 'victim_injury' => 'minor', 'offender_relationship' => 'stranger']));
        $this->post(route('incidents.store'), $this->payload(['location_description' => 'Opposite the stage 9', 'victim_type' => 'business', 'victim_injury' => 'none']));

        foreach ([['victim_sex' => 'female'], ['victim_age_band' => '65_plus'], ['victim_injury' => 'minor'], ['offender_relationship' => 'stranger'], ['victim_type' => 'business']] as $filter) {
            $total = $this->get(route('incidents.index', $filter))->assertOk()->viewData('incidents')->total();
            $this->assertSame(1, $total, (string) json_encode($filter));
        }
        $this->get(route('incidents.index', ['victim_sex' => 'unknown']))->assertSessionHasErrors('victim_sex');
    }

    /** TC-FT-72: CSV import takes the victim columns, still accepts files without them, and reports V9. */
    #[Test]
    public function the_import_reads_victim_columns_and_old_files_still_work(): void
    {
        $header = 'category,occurred_at,reported_at,sub_county,ward,station,location_description,description,status,closed_at,victim_type,victim_count,victim_sex,victim_age,victim_age_band,victim_injury,offender_relationship';
        $file = UploadedFile::fake()->createWithContent('victims.csv', $header."\n".implode("\n", [
            'Assault,2026-08-01 21:00,2026-08-01 22:00,Kibra,Makina,Kibra Sample Station,Near the market 5,Fight.,reported,,individual,1,male,33,,minor,acquaintance',
            'Burglary,2026-08-02 02:00,2026-08-02 08:00,Kibra,Makina,Kibra Sample Station,Shop 4,Padlock cut.,reported,,business,,,,,none,',
            'Assault,2026-08-03 21:00,2026-08-03 22:00,Kibra,Makina,Kibra Sample Station,Near the bar 2,Fight.,reported,,individual,1,John Doe,33,,minor,stranger',
        ])."\n");
        $this->actingAs($this->admin)->post(route('imports.store'), ['file' => $file])->assertRedirect();
        $batch = ImportBatch::query()->sole();
        $this->assertSame([3, 2, 1], [$batch->rows_total, $batch->rows_accepted, $batch->rows_rejected]);
        $this->assertSame(['V9' => 1], $batch->error_report['by_rule'] ?? null);
        $this->assertStringNotContainsString('John Doe', (string) json_encode($batch->error_report));
        $this->post(route('imports.commit', $batch))->assertRedirect();

        $assault = Incident::query()->where('victim_type', 'individual')->sole();
        $this->assertSame([VictimSex::Male, 33, AgeBand::From25To34, OffenderRelationship::Acquaintance], [$assault->victim_sex, $assault->victim_age, $assault->victim_age_band, $assault->offender_relationship]);

        $old = UploadedFile::fake()->createWithContent('old.csv', "category,occurred_at,reported_at,sub_county,ward,station,location_description,description,status,closed_at\nTheft,2026-08-05 10:00,2026-08-05 11:00,Kibra,Makina,Kibra Sample Station,Near the church 3,Phone taken.,reported,\n");
        $this->post(route('imports.store'), ['file' => $old])->assertRedirect();
        $this->assertSame(1, ImportBatch::query()->latest('id')->firstOrFail()->rows_accepted);
    }

    /** TC-AC-08: CSV and Excel exports carry the victim columns. */
    #[Test]
    public function exports_carry_the_victim_columns(): void
    {
        $this->actingAs($this->admin)->post(route('incidents.store'), $this->payload(['victim_type' => 'individual', 'victim_sex' => 'female', 'victim_age' => '29', 'victim_injury' => 'minor']));

        $lines = array_values(array_filter(explode("\n", $this->get(route('reports.csv', ['from' => '2026-09-01', 'to' => '2026-09-02']))->streamedContent())));
        $this->assertStringContainsString('victim_type,victim_count,victim_sex,victim_age,victim_age_band,victim_injury,offender_relationship,is_synthetic', $lines[0]);
        $this->assertStringContainsString('individual,,female,29,25_34,minor,not_known,sample', $lines[1]);
    }
}
