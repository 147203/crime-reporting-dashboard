<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Content Security Policy with a per-request nonce, plus the standard hardening headers.
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        Vite::useCspNonce();

        $response = $next($request);

        $nonce = Vite::cspNonce();
        $dev = Vite::isRunningHot() ? ' '.$this->devServerOrigin() : '';

        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'{$dev}",
            "style-src 'self' 'nonce-{$nonce}'{$dev}",
            "img-src 'self' data:",
            "font-src 'self'{$dev}",
            "connect-src 'self'".($dev !== '' ? $dev.' '.str_replace('http', 'ws', trim($dev)) : ''),
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        return $response;
    }

    private function devServerOrigin(): string
    {
        $url = trim((string) @file_get_contents(public_path('hot')));

        return (string) preg_replace('#^(https?://[^/]+).*$#', '$1', $url);
    }
}
