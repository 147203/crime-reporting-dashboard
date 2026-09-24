<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SecurityHeadersTest extends TestCase
{
    /** TC-SE-10: CSP with a per-request nonce and the hardening headers on every response. */
    #[Test]
    public function responses_carry_the_security_headers(): void
    {
        $first = $this->get('/login');
        $second = $this->get('/login');

        $first->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $csp = (string) $first->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringNotContainsString('unsafe-inline', $csp);
        $this->assertStringNotContainsString('unsafe-eval', $csp);

        $nonce = preg_match("/'nonce-([^']+)'/", $csp, $match) === 1 ? $match[1] : '';
        $this->assertNotSame('', $nonce);
        $this->assertStringContainsString('nonce="'.$nonce.'"', (string) $first->getContent());
        $this->assertNotSame($csp, $second->headers->get('Content-Security-Policy'));
    }
}
