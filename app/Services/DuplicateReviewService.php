<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DuplicateStatus;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Review of V8 suspected duplicates. Confirming removes the record from every analytic (it stops being countable);
 * dismissing keeps it. Nothing is merged or deleted.
 */
final class DuplicateReviewService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function resolve(Incident $incident, DuplicateStatus $decision, User $user): void
    {
        if ($incident->duplicate_status !== DuplicateStatus::Suspected || ! in_array($decision, [DuplicateStatus::Confirmed, DuplicateStatus::Dismissed], true)) {
            throw ValidationException::withMessages(['decision' => 'Only a suspected duplicate can be confirmed or dismissed.']);
        }

        // Confirming removes this record from analytics, which is only right while the earlier record is still counted.
        $original = $incident->duplicate_of_id !== null ? Incident::query()->find($incident->duplicate_of_id) : null;
        if ($decision === DuplicateStatus::Confirmed && ($original === null || $original->duplicate_status === DuplicateStatus::Confirmed)) {
            throw ValidationException::withMessages(['decision' => 'The earlier record has been deleted or is itself a confirmed duplicate, so confirming would remove this incident from every count. Dismiss the flag instead.']);
        }

        DB::transaction(function () use ($incident, $decision, $user): void {
            $incident->duplicate_status = $decision;
            $incident->updated_by = $user->id;
            $incident->save();
            $this->audit->record('duplicate.'.$decision->value, $incident, ['duplicate_of_id' => $incident->duplicate_of_id], $user);
        });
    }
}
