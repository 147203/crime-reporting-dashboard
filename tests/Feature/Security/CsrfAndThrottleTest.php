<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\AccessFixture;
use Tests\Support\RouteAccessMatrix;
use Tests\TestCase;

final class CsrfAndThrottleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Laravel skips CSRF verification while unit tests run; binding a non-testing environment turns the real check back on.
     */
    private function enableCsrfVerification(): void
    {
        $this->app->instance('env', 'local');
    }

    /** TC-SE-05 */
    #[Test]
    public function every_state_changing_route_rejects_a_request_without_a_csrf_token(): void
    {
        $this->enableCsrfVerification();
        $fixture = new AccessFixture;
        $this->actingAs($fixture->admin);

        foreach (array_keys(RouteAccessMatrix::routes()) as $key) {
            [$method, $name] = explode(' ', $key, 2);

            if ($method === 'GET') {
                continue;
            }

            $this->call($method, route($name, RouteAccessMatrix::parameters($name, $fixture)))->assertStatus(419);
        }
    }

    /** TC-SE-06 */
    #[Test]
    public function login_attempts_are_throttled_per_email_and_ip(): void
    {
        $user = User::factory()->create();
        $limit = (int) config('security.login_max_attempts');

        for ($i = 0; $i < $limit; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password-here'])->assertSessionHasErrors('email');
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password-here'])->assertStatus(429);
    }

    /** TC-SE-07 */
    #[Test]
    public function password_confirmation_is_throttled(): void
    {
        $this->actingAs(User::factory()->create());

        for ($i = 0; $i < 20; $i++) {
            $this->post(route('password.confirm.store'), ['password' => 'wrong-password-here']);
        }

        $this->post(route('password.confirm.store'), ['password' => 'wrong-password-here'])->assertStatus(429);
    }
}
