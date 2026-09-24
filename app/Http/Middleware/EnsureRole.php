<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route guard: `role:admin,analyst`. Rejects before the controller runs.
 */
final class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->hasRole(...array_map(Role::from(...), $roles)), 403);

        return $next($request);
    }
}
