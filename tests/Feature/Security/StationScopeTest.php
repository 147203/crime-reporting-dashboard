<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Incident;
use App\Models\Station;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\AccessFixture;
use Tests\TestCase;

/**
 * TC-SE-09: direct object references across stations (IDOR). An officer cannot read or change another station's record
 * by guessing its ID, and does not see it in lists.
 */
final class StationScopeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function an_officer_cannot_reach_another_stations_incident_by_id(): void
    {
        $fixture = new AccessFixture;
        $other = Incident::factory()->atStation(Station::factory()->create())->create();
        $this->actingAs($fixture->officer);

        $this->get(route('incidents.show', $other))->assertForbidden();
        $this->get(route('incidents.edit', $other))->assertForbidden();
        $this->put(route('incidents.update', $other), [])->assertForbidden();
        $this->put(route('incidents.status', $other), ['status' => 'closed'])->assertForbidden();
        $this->delete(route('incidents.destroy', $other))->assertForbidden();

        $this->get(route('incidents.index'))->assertOk()->assertSee((string) $fixture->incident->reference_no)->assertDontSee((string) $other->reference_no);
    }

    #[Test]
    public function an_officer_cannot_record_an_incident_for_another_station(): void
    {
        $fixture = new AccessFixture;
        $otherStation = Station::factory()->create();
        $this->actingAs($fixture->officer);

        $this->post(route('incidents.store'), [
            'category_id' => $fixture->incident->category_id,
            'occurred_at' => now()->subDay()->format('Y-m-d H:i'),
            'reported_at' => now()->subHours(2)->format('Y-m-d H:i'),
            'sub_county_id' => $fixture->incident->sub_county_id,
            'ward_id' => $fixture->incident->ward_id,
            'station_id' => $otherStation->id,
        ])->assertRedirect();

        $this->assertSame(0, Incident::query()->where('station_id', $otherStation->id)->count());
        $this->assertSame(2, Incident::query()->where('station_id', $fixture->station->id)->count());
    }

    #[Test]
    public function an_officer_cannot_edit_a_record_once_it_is_under_investigation_or_created_by_someone_else(): void
    {
        $fixture = new AccessFixture;
        $this->actingAs($fixture->officer);

        $colleagues = Incident::factory()->atStation($fixture->station)->create();
        $this->get(route('incidents.edit', $colleagues))->assertForbidden();

        $this->put(route('incidents.status', $fixture->incident), ['status' => 'under_investigation'])->assertRedirect();
        $this->get(route('incidents.edit', $fixture->incident))->assertForbidden();
    }
}
