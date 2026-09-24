<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Records who did what and when. Callers pass identifiers and field names only, never the content of sensitive fields.
 */
final class AuditLogger
{
    /**
     * @param  array<string, scalar|list<scalar>|null>  $meta
     */
    public function record(string $action, ?Model $entity = null, array $meta = [], ?User $actor = null): void
    {
        $actor ??= auth()->user();

        AuditLog::query()->create([
            'user_id' => $actor instanceof User ? $actor->id : null,
            'action' => $action,
            'entity_type' => $entity !== null ? class_basename($entity) : null,
            'entity_id' => $entity?->getKey(),
            'meta' => $meta === [] ? null : $meta,
            'ip_address' => request()->ip(),
        ]);
    }
}
