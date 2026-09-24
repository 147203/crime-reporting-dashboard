<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PreflightMiddlewareTest extends TestCase
{
    /** TC-SE-04 */
    #[Test]
    public function every_request_is_refused_when_a_runtime_secret_is_invalid(): void
    {
        config(['app.env' => 'production', 'database.connections.mysql.password' => 'Xq9-too-short']);

        $this->get('/login')
            ->assertStatus(503)
            ->assertDontSee('Xq9-too-short');
    }

    #[Test]
    public function requests_are_refused_when_the_app_key_is_missing(): void
    {
        config(['app.key' => '']);

        // The test client encrypts outgoing cookies by default, which itself needs APP_KEY.
        $this->disableCookieEncryption()->get('/login')->assertStatus(503);
    }
}
