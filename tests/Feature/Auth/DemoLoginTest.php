<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Station;
use App\Models\User;
use App\Support\Preflight;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * D-54: one-click demo sign-in exists only when switched on, on a local loopback machine, and never in production.
 */
final class DemoLoginTest extends TestCase
{
    use RefreshDatabase;

    private function enableDemo(): void
    {
        config(['demo.enabled' => true, 'app.env' => 'local', 'app.url' => 'http://127.0.0.1:8000']);
    }

    /** TC-SE-22: switched off (the default), the login page has no demo panel and the route does nothing. */
    #[Test]
    public function demo_sign_in_is_off_by_default(): void
    {
        $this->get(route('login'))->assertOk()->assertDontSee('Demo only');
        $this->post(route('demo.login', 'admin'))->assertNotFound();
        $this->assertGuest();
        $this->assertSame(0, User::query()->count());
    }

    /** TC-SE-23: even when switched on, demo sign-in is refused off a local loopback machine and fails preflight in production. */
    #[Test]
    public function demo_sign_in_needs_a_local_loopback_machine_and_fails_production_preflight(): void
    {
        // Off a loopback address, or outside local and testing, the rule is off. (Over HTTP such a machine is already
        // refused earlier with 503, because the local database shortcut does not apply there either.)
        foreach ([['local', 'https://crime.example.org'], ['staging', 'http://127.0.0.1:8000'], ['production', 'http://127.0.0.1:8000']] as [$env, $url]) {
            config(['demo.enabled' => true, 'app.env' => $env, 'app.url' => $url]);
            $this->assertFalse(app(Preflight::class)->demoLoginActive(), "{$env} {$url}");
            $this->assertContains($this->post(route('demo.login', 'admin'))->getStatusCode(), [404, 503], "{$env} {$url}");
            $this->assertGuest();
        }

        config(['app.env' => 'production']);
        $this->assertContains('DEMO_LOGIN must be false in production: demo sign-in skips the password.', app(Preflight::class)->runtimeFailures());
    }

    /** TC-SE-26: with everything switched on, a request from another computer, or to a non-loopback host, still gets 404. */
    #[Test]
    public function demo_sign_in_is_refused_to_other_computers_on_the_network(): void
    {
        $this->enableDemo();

        $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.20'])->get(route('login'))->assertOk()->assertDontSee('Demo only');
        $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.20'])->post(route('demo.login', 'admin'))->assertNotFound();
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->post('http://192.168.1.5/demo-login/admin')->assertNotFound();
        $this->assertGuest();
        $this->assertSame(0, User::query()->count());

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->post('http://127.0.0.1/demo-login/admin')->assertRedirect();
        $this->assertAuthenticated();
    }

    /** TC-SE-27: demo addresses are reserved, so an administrator cannot create or edit an account the button would take over. */
    #[Test]
    public function demo_addresses_are_reserved_for_the_demo_buttons(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Look Alike', 'email' => 'Demo-Admin@DEMO.invalid', 'role' => 'analyst', 'is_active' => '1', 'password_mode' => 'generate',
        ])->assertSessionHasErrors(['email' => 'Addresses ending in @demo.invalid are reserved for the demo accounts, which only the demo sign-in buttons manage.']);
        $this->assertSame(1, User::query()->count());
    }

    /** TC-FT-66: each role signs in with one click, lands on the dashboard, and the sign-in is audited. */
    #[Test]
    public function each_role_signs_in_with_one_click_and_can_switch(): void
    {
        $this->enableDemo();
        $station = Station::factory()->create();

        $this->get(route('login'))->assertOk()->assertSee('Demo only: sign in as a role')
            ->assertSee('Sign in as Administrator')->assertSee('Sign in as Reporting Officer')->assertSee('Sign in as Analyst')
            ->assertSee('In real use every person signs in with their own email and password');

        foreach (['admin' => Role::Admin, 'officer' => Role::Officer, 'analyst' => Role::Analyst] as $key => $role) {
            $this->post(route('demo.login', $key))->assertRedirect(route('home'));
            $user = auth()->user();
            $this->assertInstanceOf(User::class, $user);
            $this->assertSame($role, $user->role);
            $this->assertTrue($user->isDemoAccount());
            $this->assertFalse($user->must_change_password);
            $this->assertSame($role === Role::Officer ? $station->id : null, $user->station_id);

            $this->get(route('home'))->assertOk()->assertSee('Demo: '.$role->label())->assertSee('Switch demo user');
            $this->post(route('logout'));
            $this->assertGuest();
        }

        $this->assertSame(3, AuditLog::query()->where('action', 'auth.demo_login')->count());
        // Signing in again reuses the same three accounts.
        $this->post(route('demo.login', 'analyst'));
        $this->assertSame(3, User::query()->where('email', 'like', '%@demo.invalid')->count());
    }

    /** TC-SE-24: a demo account cannot be entered with a password, and an unknown role is refused. */
    #[Test]
    public function demo_accounts_have_no_known_password_and_unknown_roles_are_refused(): void
    {
        $this->enableDemo();
        $this->post(route('demo.login', 'admin'));
        $this->post(route('logout'));

        $this->post(route('login.store'), ['email' => 'demo-admin@demo.invalid', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/demo-login/superuser')->assertNotFound();
    }

    /** TC-FT-67: a deactivated or locked demo account is made usable again by the demo button (local demo machines only). */
    #[Test]
    public function the_demo_button_restores_a_deactivated_demo_account(): void
    {
        $this->enableDemo();
        $this->post(route('demo.login', 'analyst'));
        $this->post(route('logout'));
        User::query()->where('email', 'demo-analyst@demo.invalid')->update(['is_active' => false, 'locked_until' => now()->addHour()]);

        $this->post(route('demo.login', 'analyst'))->assertRedirect(route('home'));
        $this->assertAuthenticated();
    }
}
