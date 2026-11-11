<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Station;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Preflight;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * D-54: signs in as the demo account of a role with one click, for presentations. Answers 404 unless demo sign-in is
 * allowed for this request (see Preflight::demoLoginAllowedFor), so the route does nothing for anyone but this computer.
 * Demo addresses (@demo.invalid) are refused everywhere else, so this cannot take over a real account.
 */
final class DemoLoginController extends Controller
{
    public function __invoke(Request $request, string $role, Preflight $preflight, AuditLogger $audit): RedirectResponse
    {
        abort_unless($preflight->demoLoginAllowedFor($request), 404);
        $role = Role::tryFrom($role) ?? abort(404);
        /** @var array{name: string, email: string} $account */
        $account = config("demo.accounts.{$role->value}");

        $user = User::query()->firstOrNew(['email' => $account['email']]);
        if (! $user->exists) {
            // Nobody knows this password: a demo account can only be entered through this button.
            $user->fill(['name' => $account['name'], 'password' => Str::password(40)]);
        }
        $user->role = $role;
        $user->station_id = $role->isStationScoped() ? ($user->station_id ?? Station::query()->where('is_active', true)->orderBy('id')->value('id')) : null;
        $user->forceFill(['is_active' => true, 'must_change_password' => false, 'failed_attempts' => 0, 'locked_until' => null, 'last_login_at' => now()])->save();

        Auth::login($user);
        $request->session()->regenerate();
        $audit->record('auth.demo_login', $user, ['role' => $role->value], $user);

        return redirect()->route('home');
    }
}
