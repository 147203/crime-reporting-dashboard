<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\IncidentStatus;
use App\Enums\Role;
use App\Models\Incident;
use App\Models\User;

/**
 * docs/02 section 2, access matrix. Station scope for officers is also applied in queries (Incident::visibleTo).
 */
final class IncidentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Incident $incident): bool
    {
        return ! $user->role->isStationScoped() || $this->isOwnStation($user, $incident);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Admin) || ($user->hasRole(Role::Officer) && $user->station?->is_active === true);
    }

    public function update(User $user, Incident $incident): bool
    {
        if ($user->hasRole(Role::Admin)) {
            return true;
        }

        return $user->hasRole(Role::Officer)
            && $this->isOwnStation($user, $incident)
            && $incident->created_by === $user->id
            && $incident->status === IncidentStatus::Reported;
    }

    public function changeStatus(User $user, Incident $incident): bool
    {
        return $user->hasRole(Role::Admin) || ($user->hasRole(Role::Officer) && $this->isOwnStation($user, $incident));
    }

    public function delete(User $user, Incident $incident): bool
    {
        return $user->hasRole(Role::Admin);
    }

    public function reviewDuplicates(User $user): bool
    {
        return $user->hasRole(Role::Admin, Role::Analyst);
    }

    public function import(User $user): bool
    {
        return $user->hasRole(Role::Admin);
    }

    public function viewDataQuality(User $user): bool
    {
        return $user->hasRole(Role::Admin, Role::Analyst);
    }

    public function export(User $user): bool
    {
        return true;
    }

    private function isOwnStation(User $user, Incident $incident): bool
    {
        return $user->station_id !== null && $incident->station_id === $user->station_id;
    }
}
