<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ends a user's other sessions and invalidates any "remember me" cookie after a password change, reset or deactivation.
 */
final class SessionTerminator
{
    public function endOtherSessions(User $user, ?string $keepSessionId = null): void
    {
        DB::table('sessions')->where('user_id', $user->id)
            ->when($keepSessionId !== null, fn ($q) => $q->where('id', '!=', $keepSessionId))
            ->delete();

        $user->setRememberToken(Str::random(60));
        $user->save();
    }
}
