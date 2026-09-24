<?php

declare(strict_types=1);

namespace Tests\Feature\Incidents;

use App\Enums\IncidentStatus;
use App\Models\AuditLog;
use App\Models\CrimeCategory;
use App\Models\Incident;
use App\Models\Station;
use App\Models\User;
use App\Models\Ward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class IncidentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Station $station;

    private Ward $ward;

    private CrimeCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->station = Station::factory()->create();
        $this->ward = Ward::factory()->create(['sub_county_id' => $this->station->sub_county_id]);
        $this->category = CrimeCategory::factory()->create();
        $this->freezeTime();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'category_id' => $this->category->id,
            'occurred_at' => now()->subDay()->format('Y-m-d\TH:i'),
            'reported_at' => now()->subHours(20)->format('Y-m-d\TH:i'),
            'sub_county_id' => $this->station->sub_county_id,
            'ward_id' => $this->ward->id,
            'station_id' => $this->station->id,
            'location_description' => 'Near the <b>market</b> gate',
            'description' => 'Handbag snatched by two people on a motorbike.',
            ...$overrides,
        ];
    }

    /** TC-FT-01: officer reports an incident in their own station; reference number, labels and audit are set. */
    #[Test]
    public function an_officer_records_an_incident_for_their_station(): void
    {
        $officer = User::factory()->officer($this->station)->create();

        $response = $this->actingAs($officer)->post(route('incidents.store'), $this->payload());

        $incident = Incident::query()->sole();
        $response->assertRedirect(route('incidents.show', $incident));
        $this->assertMatchesRegularExpression('/^NRB-\d{4}-\d{6}$/', (string) $incident->reference_no);
        $this->assertSame(IncidentStatus::Reported, $incident->status);
        $this->assertTrue($incident->is_synthetic);
        $this->assertSame($officer->id, $incident->created_by);
        $this->assertSame('Near the market gate', $incident->location_description);
        $this->assertSame(1, AuditLog::query()->where('action', 'incident.created')->where('entity_id', $incident->id)->count());

        $this->get(route('incidents.show', $incident))->assertOk()->assertSee((string) $incident->reference_no)->assertSee('Handbag snatched');
    }

    /**
     * TC-FT-02: validation V1 to V6 reject bad input with a message and store nothing.
     */
    #[Test]
    public function invalid_input_is_rejected_by_rule(): void
    {
        $admin = User::factory()->admin()->create();
        $otherWard = Ward::factory()->create();
        $inactive = CrimeCategory::factory()->create(['is_active' => false]);

        $cases = [
            'V1 missing category' => [['category_id' => null], 'category_id'],
            'V1 inactive category' => [['category_id' => $inactive->id], 'category_id'],
            'V2 future' => [['occurred_at' => now()->addDay()->format('Y-m-d\TH:i'), 'reported_at' => now()->addDays(2)->format('Y-m-d\TH:i')], 'occurred_at'],
            'V2 older than 10 years' => [['occurred_at' => now()->subYears(11)->format('Y-m-d\TH:i')], 'occurred_at'],
            'V3 reported before occurred' => [['reported_at' => now()->subDays(2)->format('Y-m-d\TH:i')], 'reported_at'],
            'V4 ward outside sub-county' => [['ward_id' => $otherWard->id], 'ward_id'],
            'V5 location too long' => [['location_description' => str_repeat('a', 151)], 'location_description'],
            'V5 description too long' => [['description' => str_repeat('a', 1001)], 'description'],
            'V6 phone in description' => [['description' => 'Owner reachable on 0712 345 678'], 'description'],
            'V6 ID in location' => [['location_description' => 'House of ID 12345678'], 'location_description'],
        ];

        foreach ($cases as $label => [$override, $field]) {
            $this->actingAs($admin)->post(route('incidents.store'), $this->payload($override))->assertSessionHasErrors($field, null, 'default');
        }

        $this->assertSame(0, Incident::query()->count(), 'A rejected record was stored.');
    }

    /** TC-FT-03: status moves forward only; Closed sets closed_at and is terminal. */
    #[Test]
    public function the_status_workflow_is_enforced(): void
    {
        $officer = User::factory()->officer($this->station)->create();
        $incident = Incident::factory()->atStation($this->station)->create();
        $this->actingAs($officer);

        $this->put(route('incidents.status', $incident), ['status' => 'closed'])->assertRedirect();
        $this->assertSame(IncidentStatus::Closed, $incident->refresh()->status);
        $this->assertNotNull($incident->refresh()->closed_at);

        $this->put(route('incidents.status', $incident), ['status' => 'under_investigation'])->assertSessionHasErrors('status');
        $this->assertSame(IncidentStatus::Closed, $incident->refresh()->status);
        $this->assertSame(['from' => 'reported', 'to' => 'closed'], AuditLog::query()->where('action', 'incident.status_changed')->value('meta'));
    }

    /** TC-FT-04: soft delete hides the record from lists and records who deleted it. */
    #[Test]
    public function an_admin_soft_deletes_an_incident(): void
    {
        $admin = User::factory()->admin()->create();
        $incident = Incident::factory()->create();

        $this->actingAs($admin)->delete(route('incidents.destroy', $incident))->assertRedirect(route('incidents.index'));

        $this->assertSoftDeleted($incident);
        $this->get(route('incidents.show', $incident))->assertNotFound();
        $this->get(route('incidents.index'));
        $this->get(route('incidents.index'))->assertDontSee((string) $incident->reference_no)->assertSee('No incidents match');
        $this->assertSame(1, AuditLog::query()->where('action', 'incident.deleted')->count());
    }

    /** TC-FT-05: filters combine; search input is bound, not interpolated. */
    #[Test]
    public function filters_combine_on_the_list(): void
    {
        $admin = User::factory()->admin()->create();
        $match = Incident::factory()->create(['category_id' => $this->category->id, 'location_description' => 'Near Stage 5']);
        $otherCategory = Incident::factory()->create(['location_description' => 'Near Stage 5']);

        $this->actingAs($admin)->get(route('incidents.index', ['category_id' => $this->category->id, 'q' => 'Stage']))
            ->assertOk()->assertSee((string) $match->reference_no)->assertDontSee((string) $otherCategory->reference_no);

        $this->get(route('incidents.index', ['q' => "' OR 1=1 --"]))->assertOk()->assertSee('No incidents match');
        $this->get(route('incidents.index', ['sort' => 'password']))->assertSessionHasErrors('sort');
    }

    /** TC-SE-11: stored text is escaped on output. */
    #[Test]
    public function stored_markup_is_escaped_when_shown(): void
    {
        $admin = User::factory()->admin()->create();
        $incident = Incident::factory()->create(['description' => '<script>alert(1)</script> & more']);

        $this->actingAs($admin)->get(route('incidents.show', $incident))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }
}
