<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\ImportBatch;
use App\Models\Incident;
use App\Models\Station;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * One user per role, and an incident at the officer's own station that the officer created (status Reported).
 */
final class AccessFixture
{
    public readonly User $admin;

    public readonly User $officer;

    public readonly User $analyst;

    public readonly Station $station;

    public readonly Incident $incident;

    public readonly ImportBatch $batch;

    public function __construct()
    {
        $this->station = Station::factory()->create();
        $this->admin = User::factory()->admin()->create();
        $this->officer = User::factory()->officer($this->station)->create();
        $this->analyst = User::factory()->analyst()->create();
        $this->incident = Incident::factory()->atStation($this->station)->create();
        DB::table('incidents')->where('id', $this->incident->id)->update(['created_by' => $this->officer->id]);
        $this->incident->refresh();
        $this->batch = ImportBatch::query()->create(['user_id' => $this->admin->id, 'file_name' => 'fixture.csv', 'status' => ImportBatch::STATUS_DISCARDED, 'error_report' => ['total' => 0, 'truncated' => false, 'errors' => []]]);
    }

    public function actor(string $name): ?User
    {
        return match ($name) {
            'admin' => $this->admin,
            'officer' => $this->officer,
            'analyst' => $this->analyst,
            default => null,
        };
    }
}
