<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DuplicateStatus;
use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class IncidentService
{
    public function __construct(private readonly AuditLogger $audit, private readonly DuplicateDetector $duplicates) {}

    /**
     * @param  array<string, mixed>  $data  validated input, including station_id resolved for the user's role
     */
    public function create(array $data, User $user): Incident
    {
        return DB::transaction(function () use ($data, $user): Incident {
            $incident = new Incident($data);
            $incident->station_id = (int) $data['station_id'];
            $incident->status = IncidentStatus::Reported;
            $incident->created_by = $user->id;
            $incident->updated_by = $user->id;
            $this->flagDuplicate($incident);
            $incident->save();

            $incident->reference_no = sprintf('NRB-%s-%06d', $incident->created_at?->format('Y') ?? now()->format('Y'), $incident->id);
            $incident->save();

            $this->audit->record('incident.created', $incident, ['station_id' => $incident->station_id], $user);

            return $incident;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Incident $incident, array $data, User $user): Incident
    {
        return DB::transaction(function () use ($incident, $data, $user): Incident {
            $incident->fill($data);

            if (array_key_exists('station_id', $data)) {
                $incident->station_id = (int) $data['station_id'];
            }

            $changed = array_keys($incident->getDirty());

            if ($incident->duplicate_status === DuplicateStatus::None && array_intersect($changed, ['category_id', 'ward_id', 'occurred_at', 'location_description']) !== []) {
                $this->flagDuplicate($incident);
            }

            if ($changed !== []) {
                $incident->updated_by = $user->id;
                $incident->save();
                $this->audit->record('incident.updated', $incident, ['fields' => $changed], $user);
            }

            return $incident;
        });
    }

    public function changeStatus(Incident $incident, IncidentStatus $target, User $user): Incident
    {
        if (! $incident->status->canMoveTo($target)) {
            throw ValidationException::withMessages(['status' => "An incident that is {$incident->status->label()} cannot move to {$target->label()}."]);
        }

        return DB::transaction(function () use ($incident, $target, $user): Incident {
            $from = $incident->status;
            $incident->status = $target;
            $incident->closed_at = $target === IncidentStatus::Closed ? now() : null;
            $incident->updated_by = $user->id;
            $incident->save();

            $this->audit->record('incident.status_changed', $incident, ['from' => $from->value, 'to' => $target->value], $user);

            return $incident;
        });
    }

    public function delete(Incident $incident, User $user): void
    {
        DB::transaction(function () use ($incident, $user): void {
            // Deleting the original of a confirmed duplicate would remove the event from every analytic, because the
            // confirmed copy is already excluded. Checked under a lock so a confirmation cannot slip in between.
            if (Incident::query()->where('duplicate_of_id', $incident->id)->where('duplicate_status', DuplicateStatus::Confirmed->value)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['delete' => 'This record has confirmed duplicates pointing to it and is the one counted in analytics, so it cannot be deleted.']);
            }

            $incident->updated_by = $user->id;
            $incident->save();
            $incident->delete();

            $this->audit->record('incident.deleted', $incident, [], $user);
        });
    }

    private function flagDuplicate(Incident $incident): void
    {
        $match = $this->duplicates->findMatch($incident);

        if ($match !== null) {
            $incident->duplicate_status = DuplicateStatus::Suspected;
            $incident->duplicate_of_id = $match->id;
        }
    }
}
