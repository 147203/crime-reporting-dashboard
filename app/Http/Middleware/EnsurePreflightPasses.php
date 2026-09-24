<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Preflight;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePreflightPasses
{
    public function __construct(private readonly Preflight $preflight) {}

    public function handle(Request $request, Closure $next): Response
    {
        $failures = $this->preflight->runtimeFailures();

        if ($failures !== []) {
            Log::critical('Preflight failed; refusing to serve requests.', ['failures' => $failures]);

            // Built directly: abort() and the response() helper both resolve the encrypted session, which fails without APP_KEY.
            return new Response('The application is not configured. The operator must run php artisan app:preflight.', 503, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        }

        return $next($request);
    }
}
