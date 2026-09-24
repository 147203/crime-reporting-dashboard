<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DuplicateStatus;
use App\Models\ImportBatch;
use App\Models\Incident;
use App\Models\User;

/**
 * FR-13 data-quality summary (definitions in docs/02 section 5a):
 *  - rejections by rule: counts over every error of every committed import (not the truncated list)
 *  - duplicate review: records the viewer can see, by duplicate status
 *  - completeness: share of countable records with the optional field filled in
 */
final class QualityReport
{
    /**
     * @return array<string, mixed>
     */
    public function for(User $viewer): array
    {
        // Only the per-rule counts are read; the full error lists can be large.
        $imports = ImportBatch::query()->where('status', ImportBatch::STATUS_COMMITTED)
            ->toBase()->selectRaw("rows_total, rows_accepted, rows_rejected, JSON_EXTRACT(error_report, '$.by_rule') as by_rule")->get();
        $rejections = [];
        foreach ($imports as $batch) {
            $byRule = json_decode((string) ($batch->by_rule ?? 'null'), true);
            foreach (is_array($byRule) ? $byRule : [] as $rule => $count) {
                $rejections[(string) $rule] = ($rejections[(string) $rule] ?? 0) + (int) $count;
            }
        }
        ksort($rejections);

        $visible = Incident::query()->visibleTo($viewer);
        $byDuplicate = (clone $visible)->toBase()->selectRaw('duplicate_status, count(*) as n')->groupBy('duplicate_status')->pluck('n', 'duplicate_status');

        $countable = Incident::query()->visibleTo($viewer)->countable();
        $total = (clone $countable)->count();
        $withLocation = (clone $countable)->whereNotNull('location_description')->count();
        $withDescription = (clone $countable)->whereNotNull('description')->count();

        return [
            'importCount' => $imports->count(),
            'rowsTotal' => (int) $imports->sum('rows_total'),
            'rowsAccepted' => (int) $imports->sum('rows_accepted'),
            'rowsRejected' => (int) $imports->sum('rows_rejected'),
            'rejections' => $rejections,
            'visible' => (clone $visible)->count(),
            'countable' => $total,
            'duplicates' => collect(DuplicateStatus::cases())->mapWithKeys(fn (DuplicateStatus $s) => [$s->label() => (int) ($byDuplicate[$s->value] ?? 0)])->all(),
            'completeness' => [
                'Street or landmark' => $total > 0 ? round(100 * $withLocation / $total, 2) : null,
                'Description' => $total > 0 ? round(100 * $withDescription / $total, 2) : null,
            ],
        ];
    }
}
