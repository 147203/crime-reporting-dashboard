<?php

declare(strict_types=1);

namespace Tests\Feature\Schema;

use App\Models\AuditLog;
use App\Models\Incident;
use App\Models\Station;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AuditAndMassAssignmentTest extends TestCase
{
    use RefreshDatabase;

    /** TC-FT-19: append-only through Eloquent and through raw SQL. */
    #[Test]
    public function audit_entries_cannot_be_changed_or_removed(): void
    {
        $entry = AuditLog::query()->create(['action' => 'test.event']);

        try {
            $entry->update(['action' => 'rewritten']);
            $this->fail('Eloquent update was allowed.');
        } catch (LogicException) {
            $this->addToAssertionCount(1);
        }

        foreach ([fn () => DB::table('audit_logs')->update(['action' => 'rewritten']), fn () => DB::table('audit_logs')->delete()] as $write) {
            try {
                $write();
                $this->fail('A raw write to audit_logs was allowed.');
            } catch (QueryException $exception) {
                $this->assertStringContainsString('append-only', $exception->getMessage());
            }
        }

        $this->assertSame('test.event', DB::table('audit_logs')->value('action'));
    }

    /** TC-SE-08: mass assignment cannot set station, status, duplicate or role fields. */
    #[Test]
    public function protected_fields_are_not_mass_assignable(): void
    {
        $incident = new Incident(['station_id' => 999, 'status' => 'closed', 'duplicate_status' => 'confirmed', 'is_synthetic' => false, 'reference_no' => 'X', 'created_by' => 1]);
        $user = new User(['role' => 'admin', 'station_id' => 1, 'is_active' => false, 'must_change_password' => false, 'failed_attempts' => 9]);

        $this->assertSame([], array_intersect_key($incident->getAttributes(), array_flip(['station_id', 'status', 'duplicate_status', 'is_synthetic', 'reference_no', 'created_by'])));
        $this->assertSame([], array_intersect_key($user->getAttributes(), array_flip(['role', 'station_id', 'is_active', 'must_change_password', 'failed_attempts'])));
    }

    /** TC-FT-20: users are deactivated, never deleted, so provenance survives. */
    #[Test]
    public function a_user_who_created_incidents_cannot_be_deleted(): void
    {
        $station = Station::factory()->create();
        $user = User::factory()->officer($station)->create();
        $incident = Incident::factory()->atStation($station)->create();
        DB::table('incidents')->where('id', $incident->id)->update(['created_by' => $user->id]);

        $this->expectException(QueryException::class);

        $user->delete();
    }
}
