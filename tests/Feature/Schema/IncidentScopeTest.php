<?php

declare(strict_types=1);

namespace Tests\Feature\Schema;

use App\Enums\DuplicateStatus;
use App\Models\Incident;
use App\Models\Station;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class IncidentScopeTest extends TestCase
{
    use RefreshDatabase;

    /** TC-FT-13: station scope is enforced in the query, not only in the UI. */
    #[Test]
    public function an_officer_sees_only_their_station_and_other_roles_see_all(): void
    {
        $own = Station::factory()->create();
        $other = Station::factory()->create();
        Incident::factory()->atStation($own)->count(2)->create();
        Incident::factory()->atStation($other)->count(3)->create();

        $this->assertSame(2, Incident::query()->visibleTo(User::factory()->officer($own)->create())->count());
        $this->assertSame(5, Incident::query()->visibleTo(User::factory()->analyst()->create())->count());
        $this->assertSame(5, Incident::query()->visibleTo(User::factory()->admin()->create())->count());
    }

    /** TC-FT-14 */
    #[Test]
    public function an_officer_without_a_station_sees_nothing(): void
    {
        Incident::factory()->count(2)->create();
        $officer = User::factory()->create(['role' => 'officer', 'station_id' => null]);

        $this->assertSame(0, Incident::query()->visibleTo($officer)->count());
    }

    /** TC-FT-15: countable excludes soft-deleted and confirmed duplicates (docs/02 section 4). */
    #[Test]
    public function countable_excludes_deleted_and_confirmed_duplicates(): void
    {
        Incident::factory()->count(3)->create();
        Incident::factory()->create()->delete();
        $duplicate = Incident::factory()->create();
        $duplicate->duplicate_status = DuplicateStatus::Confirmed;
        $duplicate->save();
        $suspected = Incident::factory()->create();
        $suspected->duplicate_status = DuplicateStatus::Suspected;
        $suspected->save();

        $this->assertSame(4, Incident::query()->countable()->count());
    }
}
