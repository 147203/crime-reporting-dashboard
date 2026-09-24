<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Auth\AuthenticateUser;
use App\Enums\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        Fortify::loginView(fn () => view('auth.login'));
        Fortify::confirmPasswordView(fn () => view('auth.confirm-password'));
        Fortify::authenticateUsing(fn (Request $request) => app(AuthenticateUser::class)($request));

        Gate::define('manage-system', fn (User $user): bool => $user->hasRole(Role::Admin));
        Gate::define('change-own-password', fn (User $user): bool => $user->is_active);

        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User) {
                app(AuditLogger::class)->record('auth.login', $event->user, [], $event->user);
            }
        });
        Event::listen(Logout::class, function (Logout $event): void {
            if ($event->user instanceof User) {
                app(AuditLogger::class)->record('auth.logout', $event->user, [], $event->user);
            }
        });

        RateLimiter::for('login', function (Request $request): Limit {
            $email = Str::transliterate(Str::lower((string) $request->input(Fortify::username())));

            return Limit::perMinute((int) config('security.login_max_attempts'))->by($email.'|'.$request->ip());
        });

        RateLimiter::for('password-change', fn (Request $request): Limit => Limit::perMinute(5)->by('password|'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('auth-actions', fn (Request $request): Limit => Limit::perMinute(20)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }
}
