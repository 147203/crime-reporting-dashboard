<?php

declare(strict_types=1);

namespace Tests\Feature\Quality;

use App\Enums\DuplicateStatus;
use App\Models\Incident;
use App\Models\Station;
use App\Models\User;
use App\Models\Ward;
use App\Services\Analytics\Analytics;
use App\Services\Analytics\DashboardViewModel;
use App\Support\Filters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * TC-FT-58: every dashboard drill-down link opens a list whose total equals the value shown on the chart (proposal:
 * interactive visualisation).
 */
final class DrillDownTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function every_drill_down_list_has_as_many_records_as_its_chart_value(): void
    {
        $this->seedIncidents();

        $this->assertDrillDownTotalsMatch(User::factory()->admin()->create(), 15);
    }

    #[Test]
    public function an_officers_drill_down_stays_within_their_station(): void
    {
        $station = $this->seedIncidents();

        $this->assertDrillDownTotalsMatch(User::factory()->officer($station)->create(), 3);
    }

    #[Test]
    public function a_ward_link_opens_that_ward_when_another_sub_county_has_a_ward_of_the_same_name(): void
    {
        $this->travelTo('2026-09-02 08:00:00');
        $first = Ward::factory()->create(['name' => 'Central']);
        $second = Ward::factory()->create(['name' => 'Central']);
        Incident::factory()->count(3)->create(['ward_id' => $first->id, 'occurred_at' => '2026-08-04 09:30', 'reported_at' => '2026-08-04 09:30']);
        Incident::factory()->count(1)->create(['ward_id' => $second->id, 'occurred_at' => '2026-08-04 09:30', 'reported_at' => '2026-08-04 09:30']);

        $this->assertDrillDownTotalsMatch(User::factory()->admin()->create(), 4);
    }

    private function seedIncidents(): Station
    {
        $this->travelTo('2026-09-02 08:00:00');
        foreach (['2026-08-03 02:00', '2026-08-04 09:30', '2026-08-08 14:00', '2026-08-09 21:15', '2026-07-12 19:00', '2026-07-14 07:45'] as $at) {
            Incident::factory()->count(2)->create(['occurred_at' => $at, 'reported_at' => $at]);
        }
        $confirmed = Incident::factory()->create(['occurred_at' => '2026-08-04 10:00', 'reported_at' => '2026-08-04 10:00']);
        $confirmed->duplicate_status = DuplicateStatus::Confirmed;
        $confirmed->save();

        $station = Station::factory()->create();
        foreach (['2026-08-03 02:00', '2026-08-08 14:00', '2026-07-12 19:00'] as $at) {
            Incident::factory()->atStation($station)->create(['occurred_at' => $at, 'reported_at' => $at]);
        }

        return $station;
    }

    private function assertDrillDownTotalsMatch(User $user, int $expectedN): void
    {
        $filters = Filters::fromValidated(['from' => '2026-07-01', 'to' => '2026-08-31']);
        $vm = new DashboardViewModel((new Analytics($user, $filters))->all(), $filters);
        $this->actingAs($user);

        $checks = [
            'category' => [$vm->categories()['links'], array_column($vm->metrics['A2'], 'count')],
            'weekday' => [$vm->weekdays()['links'], array_column($vm->metrics['A5'], 'count')],
            'band' => [$vm->timeBands()['links'], array_column($vm->metrics['A6'], 'count')],
            'month' => [$vm->trend()['links'], array_column($vm->metrics['A3'], 'count')],
            'status' => [$vm->statuses()['links'], array_values($vm->metrics['A10']['status'])],
            'ward' => [$vm->topWards()['links'], array_column(array_slice($vm->metrics['A7']['wards'], 0, 10), 'count')],
        ];

        $compared = 0;
        foreach ($checks as $kind => [$links, $values]) {
            $this->assertCount(count($values), $links, $kind);
            foreach ($links as $i => $url) {
                $incidents = $this->get($url)->assertOk()->viewData('incidents');
                $this->assertInstanceOf(LengthAwarePaginator::class, $incidents);
                $this->assertSame($values[$i], $incidents->total(), "{$kind} #{$i}: {$url}");
                $compared++;
            }
        }

        $this->assertSame($expectedN, $vm->n());
        $this->assertGreaterThan(10, $compared);
    }
}
