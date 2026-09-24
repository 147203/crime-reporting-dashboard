<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends the session of an account deactivated while signed in, and sends users who must change their password to the
 * password page before anything else.
 */
final class EnsureAccountUsable
{
    private const ALLOWED_WHILE_CHANGE_PENDING = ['password.edit', 'password.update', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'This account has been deactivated.']);
        }

        if ($user instanceof User && $user->must_change_password && ! $request->routeIs(...self::ALLOWED_WHILE_CHANGE_PENDING)) {
            return redirect()->route('password.edit')->with('warning', 'Please set a new password before continuing.');
        }

        return $next($request);
    }
}
