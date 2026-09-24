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
        if (! in_array($decision, [DuplicateStatus::Confirmed, DuplicateStatus::Dismissed], true)) {
            throw ValidationException::withMessages(['decision' => 'Choose confirm or dismiss.']);
        }

        DB::transaction(function () use ($incident, $decision, $user): void {
            // Both rows are locked (lower id first, so two reviews cannot deadlock) and re-checked inside the transaction,
            // so a parallel decision or a delete of the earlier record cannot slip in between.
            $ids = array_values(array_filter([$incident->id, $incident->duplicate_of_id]));
            sort($ids);
            $rows = Incident::withTrashed()->whereKey($ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            $current = $rows->get($incident->id);
            if ($current === null || $current->trashed() || $current->duplicate_status !== DuplicateStatus::Suspected) {
                throw ValidationException::withMessages(['decision' => 'Only a suspected duplicate can be confirmed or dismissed.']);
            }

            // Confirming removes this record from analytics, which is only right while the earlier record is counted.
            $original = $current->duplicate_of_id !== null ? $rows->get($current->duplicate_of_id) : null;
            if ($decision === DuplicateStatus::Confirmed && ($original === null || $original->trashed() || $original->duplicate_status === DuplicateStatus::Confirmed)) {
                throw ValidationException::withMessages(['decision' => 'The earlier record has been deleted or is itself a confirmed duplicate, so confirming would remove this incident from every count. Dismiss the flag instead.']);
            }

            $current->duplicate_status = $decision;
            $current->updated_by = $user->id;
            $current->save();
            $this->audit->record('duplicate.'.$decision->value, $current, ['duplicate_of_id' => $current->duplicate_of_id], $user);
        });
    }
}
