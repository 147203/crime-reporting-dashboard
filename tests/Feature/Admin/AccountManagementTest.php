<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Station;
use App\Models\User;
use App\Support\PasswordGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AccountManagementTest extends TestCase
{
    use RefreshDatabase;

    /** TC-UT-17: generated passwords are long, mixed, free of look-alike characters and different each time. */
    #[Test]
    public function generated_passwords_are_strong_and_unique(): void
    {
        $generator = new PasswordGenerator;
        $seen = [];

        for ($i = 0; $i < 200; $i++) {
            $password = $generator->generate();
            $this->assertSame(16, strlen($password));
            $this->assertMatchesRegularExpression('/[A-Z]/', $password);
            $this->assertMatchesRegularExpression('/[a-z]/', $password);
            $this->assertMatchesRegularExpression('/[2-9]/', $password);
            $this->assertMatchesRegularExpression('/[!#%+\-=?@_]/', $password);
            $this->assertDoesNotMatchRegularExpression('/[0O1lI]/', $password);
            $seen[$password] = true;
        }

        $this->assertCount(200, $seen);
    }

    /** TC-FT-55: a generated password is shown once, works, forces a change, and never reaches the audit log. */
    #[Test]
    public function a_generated_password_is_shown_once_and_never_logged(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'New Analyst', 'email' => 'analyst@example.test', 'role' => 'analyst', 'is_active' => '1', 'password_mode' => 'generate',
        ]);

        $response->assertRedirect(route('admin.users.index'))->assertSessionHas('generated_password');
        $password = session('generated_password')['password'];
        $user = User::query()->where('email', 'analyst@example.test')->sole();

        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertTrue($user->must_change_password);
        $shown = $this->get(route('admin.users.index'))->assertSee($password)->assertSee('will not be shown again');
        $this->assertStringContainsString('no-store', (string) $shown->headers->get('Cache-Control'));
        $this->get(route('admin.users.index'))->assertDontSee($password);
        $this->assertStringNotContainsString($password, (string) json_encode(AuditLog::query()->get()->toArray()));
    }

    /** TC-SE-19: an administrator cannot reset their own password from the admin form (it needs the current password). */
    #[Test]
    public function an_administrator_cannot_reset_their_own_password_from_the_admin_form(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'my-real-password-1']);
        User::factory()->admin()->create();

        foreach (['generate', 'manual'] as $mode) {
            $this->actingAs($admin)->put(route('admin.users.update', $admin), [
                'name' => $admin->name, 'email' => $admin->email, 'password_mode' => $mode,
                'password' => 'taken-over-pass-9', 'password_confirmation' => 'taken-over-pass-9',
            ])->assertSessionHasErrors('password_mode');
        }

        $this->assertTrue(Hash::check('my-real-password-1', (string) $admin->refresh()->password));
        $this->get(route('admin.users.edit', $admin))->assertOk()->assertSee('your own account')->assertDontSee('Generate a secure temporary password');
    }

    /** TC-SE-20: of two administrators, one may demote the other, after which the demoted one can no longer act. */
    #[Test]
    public function two_administrators_cannot_leave_the_system_without_one(): void
    {
        $first = User::factory()->admin()->create();
        $second = User::factory()->admin()->create();
        $payload = fn (User $user, string $role): array => ['name' => $user->name, 'email' => $user->email, 'role' => $role, 'is_active' => '1', 'password_mode' => 'keep'];

        $this->actingAs($first)->put(route('admin.users.update', $second), $payload($second, 'analyst'))->assertSessionHasNoErrors();
        $this->actingAs($second->refresh())->put(route('admin.users.update', $first), $payload($first, 'analyst'))->assertForbidden();

        $this->assertSame(Role::Admin, $first->refresh()->role);
    }

    /**
     * TC-SE-20: the guard itself, as defence in depth. The only way to reach the last active administrator from
     * another account is a request already in flight when the acting account lost its rights; the guard (under a row
     * lock) still refuses it. In the test the in-flight request is simulated by changing the actor in the database
     * while the test keeps its signed-in object.
     */
    #[Test]
    public function the_last_active_administrator_is_protected(): void
    {
        $last = User::factory()->admin()->create();
        $actor = User::factory()->admin()->create();
        $payload = fn (array $change): array => ['name' => $last->name, 'email' => $last->email, 'role' => 'admin', 'is_active' => '1', 'password_mode' => 'keep', ...$change];

        $this->actingAs($actor);
        User::query()->whereKey($actor->id)->update(['is_active' => false]);

        $this->put(route('admin.users.update', $last), $payload(['role' => 'analyst']))->assertSessionHasErrors('role');
        $this->put(route('admin.users.update', $last), $payload(['is_active' => '0']))->assertSessionHasErrors('role');
        $this->assertSame(Role::Admin, $last->refresh()->role);
        $this->assertTrue($last->is_active);
    }

    /** TC-FT-56: an administrator can unlock a locked account without resetting its password. */
    #[Test]
    public function an_administrator_unlocks_a_locked_account(): void
    {
        $admin = User::factory()->admin()->create();
        $locked = User::factory()->officer(Station::factory()->create())->create(['failed_attempts' => 0, 'locked_until' => now()->addMinutes(10)]);

        $this->actingAs($admin)->get(route('admin.users.index'))->assertSee('Unlock');
        $this->post(route('admin.users.unlock', $locked))->assertRedirect(route('admin.users.index'));

        $this->assertFalse($locked->refresh()->isLocked());
        $this->assertSame(1, AuditLog::query()->where('action', 'user.unlocked')->count());
    }

    /** TC-FT-57: every password field has a show or hide control. */
    #[Test]
    public function password_fields_have_a_show_hide_control(): void
    {
        $this->get('/login')->assertSee('data-password-toggle="password"', false)->assertSee('aria-label="Show password"', false);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('password.edit'))->assertSee('data-password-toggle="current_password"', false)->assertSee('data-password-toggle="password_confirmation"', false);
        $this->get(route('admin.users.create'))->assertSee('data-password-toggle="password"', false);
    }
}
