<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function attempt(string $email, string $password): void
    {
        $this->post('/login', ['email' => $email, 'password' => $password]);
    }

    /** TC-SE-03: lockout after the configured number of failures, even with the right password afterwards. */
    #[Test]
    public function the_account_locks_after_repeated_failures_and_unlocks_after_the_lockout_period(): void
    {
        $user = User::factory()->create(['password' => 'correct-horse-battery-1']);

        for ($i = 0; $i < (int) config('security.login_max_attempts'); $i++) {
            $this->attempt($user->email, 'wrong-password-123');
        }

        $this->assertTrue($user->refresh()->isLocked());
        $this->app['cache']->clear();

        $this->post('/login', ['email' => $user->email, 'password' => 'correct-horse-battery-1'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertSame(1, AuditLog::query()->where('action', 'auth.locked')->count());

        $this->travel((int) config('security.login_lockout_minutes') + 1)->minutes();
        $this->post('/login', ['email' => $user->email, 'password' => 'correct-horse-battery-1'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->assertSame(0, $user->refresh()->failed_attempts);
    }

    /** TC-FT-21: FR-03 deactivated users cannot sign in. */
    #[Test]
    public function an_inactive_account_cannot_sign_in(): void
    {
        $user = User::factory()->create(['password' => 'correct-horse-battery-1', 'is_active' => false]);

        $this->attempt($user->email, 'correct-horse-battery-1');

        $this->assertGuest();
        $this->assertSame(1, AuditLog::query()->where('action', 'auth.blocked')->count());
    }

    /** TC-FT-22 */
    #[Test]
    public function a_session_ends_when_the_account_is_deactivated(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->forceFill(['is_active' => false])->save();

        $this->get('/')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    /** TC-FT-23: first-login password change is enforced on every page. */
    #[Test]
    public function a_user_who_must_change_their_password_is_sent_to_the_password_page(): void
    {
        $user = User::factory()->create(['must_change_password' => true, 'password' => 'initial-password-1']);
        $this->actingAs($user);

        $this->get(route('incidents.index'))->assertRedirect(route('password.edit'));
        $this->get(route('password.edit'))->assertOk();

        $this->put(route('password.update'), [
            'current_password' => 'initial-password-1',
            'password' => 'my-own-password-2',
            'password_confirmation' => 'my-own-password-2',
        ])->assertRedirect(route('home'));

        $this->assertFalse($user->refresh()->must_change_password);
        $this->assertTrue(Hash::check('my-own-password-2', (string) $user->refresh()->password));
        $this->get(route('incidents.index'))->assertOk();
    }

    /** TC-FT-24: password policy (12+ characters, letters and numbers, different from current). */
    #[Test]
    public function a_weak_or_unchanged_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => 'initial-password-1']);
        $this->actingAs($user);

        foreach (['short1', 'onlyletterslongenough', 'initial-password-1'] as $candidate) {
            $this->put(route('password.update'), ['current_password' => 'initial-password-1', 'password' => $candidate, 'password_confirmation' => $candidate])
                ->assertSessionHasErrors('password');
        }

        $this->put(route('password.update'), ['current_password' => 'not-my-password-9', 'password' => 'my-own-password-2', 'password_confirmation' => 'my-own-password-2'])
            ->assertSessionHasErrors('current_password');
    }

    /** TC-FT-25: logins and logouts are audited without secrets. */
    #[Test]
    public function login_and_logout_are_audited_without_the_password(): void
    {
        $user = User::factory()->create(['password' => 'correct-horse-battery-1']);

        $this->attempt($user->email, 'correct-horse-battery-1');
        $this->post('/logout');

        $this->assertSame(['auth.login', 'auth.logout'], AuditLog::query()->orderBy('id')->pluck('action')->all());
        $this->assertStringNotContainsString('correct-horse-battery-1', (string) json_encode(AuditLog::query()->get()->toArray()));
    }
}
