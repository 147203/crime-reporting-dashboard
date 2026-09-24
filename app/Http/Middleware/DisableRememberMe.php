<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sessions end when the browser closes or after SESSION_LIFETIME; long-lived "remember me" cookies are not offered, so a
 * posted remember flag is ignored.
 */
final class DisableRememberMe
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->request->remove('remember');

        return $next($request);
    }
}
