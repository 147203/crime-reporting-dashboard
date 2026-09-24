<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Fortify login callback: account lockout after repeated failures (FR-01) and refusal of deactivated accounts (FR-03).
 * Each attempt holds a row lock on the account, so parallel guesses are counted one by one and cannot overtake the
 * lockout.
 */
final class AuthenticateUser
{
    /**
     * Hash of a random value; checked when the e-mail is unknown so both paths cost the same time.
     */
    private const TIMING_HASH = '$2y$12$HCjCLcmXKMh4tcG420Et8OCGjTRIAPw.CFBKk42FlPq9xra81GVgK';

    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(Request $request): ?User
    {
        $password = (string) $request->input('password');
        $email = Str::lower((string) $request->input('email'));

        if (! User::query()->where('email', $email)->exists()) {
            Hash::check($password, self::TIMING_HASH);
            $this->audit->record('auth.failed', meta: ['reason' => 'unknown_account']);

            return null;
        }

        $locked = null;
        $user = DB::transaction(function () use ($email, $password, &$locked): ?User {
            $user = User::query()->where('email', $email)->lockForUpdate()->firstOrFail();

            if ($user->isLocked()) {
                $locked = $user;

                return null;
            }

            if (! Hash::check($password, $user->password)) {
                $this->registerFailure($user);

                return null;
            }

            if (! $user->is_active) {
                $this->audit->record('auth.blocked', $user, ['reason' => 'inactive'], $user);

                return null;
            }

            $user->forceFill(['failed_attempts' => 0, 'locked_until' => null, 'last_login_at' => now()])->save();

            return $user;
        });

        if ($locked instanceof User) {
            // Recorded after the transaction; an exception inside it would roll the audit entry back.
            $this->audit->record('auth.blocked', $locked, ['reason' => 'locked'], $locked);

            // Telling the user the account is locked reveals that it exists; accepted so a locked-out officer knows to
            // wait or ask an administrator (D-39).
            throw ValidationException::withMessages([
                'email' => 'This account is temporarily locked after repeated failed attempts. Try again later or ask an administrator.',
            ]);
        }

        return $user;
    }

    private function registerFailure(User $user): void
    {
        $attempts = $user->failed_attempts + 1;

        if ($attempts >= (int) config('security.login_max_attempts')) {
            $user->forceFill(['failed_attempts' => 0, 'locked_until' => now()->addMinutes((int) config('security.login_lockout_minutes'))])->save();
            $this->audit->record('auth.locked', $user, ['attempts' => $attempts], $user);

            return;
        }

        $user->forceFill(['failed_attempts' => $attempts])->save();
        $this->audit->record('auth.failed', $user, ['reason' => 'wrong_password', 'attempts' => $attempts], $user);
    }
}
