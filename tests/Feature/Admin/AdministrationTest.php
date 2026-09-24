<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\CrimeCategory;
use App\Models\Station;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\PendingCommand;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

final class AdministrationTest extends TestCase
{
    use RefreshDatabase;

    /** TC-FT-26: FR-03 admin creates an officer; role and station come from the form, first login forces a change. */
    #[Test]
    public function an_admin_creates_a_reporting_officer(): void
    {
        $admin = User::factory()->admin()->create();
        $station = Station::factory()->create();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Sample Officer',
            'email' => 'Officer@Example.test',
            'role' => 'officer',
            'station_id' => $station->id,
            'is_active' => '1',
            'password' => 'temporary-pass-42',
            'password_confirmation' => 'temporary-pass-42',
        ])->assertRedirect(route('admin.users.index'));

        $user = User::query()->where('email', 'officer@example.test')->sole();
        $this->assertSame(Role::Officer, $user->role);
        $this->assertSame($station->id, $user->station_id);
        $this->assertTrue($user->must_change_password);
        $this->assertTrue(Hash::check('temporary-pass-42', $user->password));
        $this->assertSame(1, AuditLog::query()->where('action', 'user.created')->count());
    }

    /** TC-FT-27 */
    #[Test]
    public function an_officer_needs_a_station_and_a_non_officer_has_none(): void
    {
        $admin = User::factory()->admin()->create();
        $base = ['name' => 'X', 'password' => 'temporary-pass-42', 'password_confirmation' => 'temporary-pass-42'];

        $this->actingAs($admin)->post(route('admin.users.store'), [...$base, 'email' => 'a@example.test', 'role' => 'officer'])->assertSessionHasErrors('station_id');

        $station = Station::factory()->create();
        $this->post(route('admin.users.store'), [...$base, 'email' => 'b@example.test', 'role' => 'analyst', 'station_id' => $station->id])->assertRedirect();
        $this->assertNull(User::query()->where('email', 'b@example.test')->value('station_id'));
    }

    /** TC-FT-28: reset password, deactivate, and the admin cannot lock themselves out. */
    #[Test]
    public function an_admin_resets_a_password_and_deactivates_a_user_but_not_themselves(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->analyst()->create(['failed_attempts' => 3]);

        $this->actingAs($admin)->put(route('admin.users.update', $user), [
            'name' => $user->name, 'email' => $user->email, 'role' => 'analyst', 'is_active' => '0',
            'password' => 'reset-password-77', 'password_confirmation' => 'reset-password-77',
        ])->assertRedirect();

        $user->refresh();
        $this->assertFalse($user->is_active);
        $this->assertTrue($user->must_change_password);
        $this->assertSame(0, $user->failed_attempts);
        $this->assertSame(1, AuditLog::query()->where('action', 'user.password_reset')->count());
        $this->assertStringNotContainsString('reset-password-77', (string) json_encode(AuditLog::query()->get()->toArray()));

        $this->put(route('admin.users.update', $admin), ['name' => $admin->name, 'email' => $admin->email, 'role' => 'analyst', 'is_active' => '1'])
            ->assertSessionHasErrors('role');
        $this->assertSame(Role::Admin, $admin->refresh()->role);
    }

    /** TC-FT-29: FR-04 reference data is added, renamed and deactivated; inactive categories leave the form. */
    #[Test]
    public function an_admin_manages_reference_data(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('admin.reference.store', ['type' => 'categories']), ['name' => 'Fraud', 'is_active' => '1'])->assertRedirect();
        $category = CrimeCategory::query()->where('name', 'Fraud')->sole();

        $this->put(route('admin.reference.update', ['type' => 'categories', 'item' => $category->id]), ['name' => 'Fraud and forgery', 'is_active' => '0'])->assertRedirect();

        $this->assertSame('Fraud and forgery', $category->refresh()->name);
        $this->assertFalse($category->refresh()->is_active);
        $this->get(route('incidents.create'))->assertOk()->assertDontSee('Fraud and forgery');
        $this->get(route('admin.reference.index', ['tab' => 'categories']))->assertOk()->assertSee('Fraud and forgery');
        $this->get(route('admin.audit.index'))->assertOk()->assertSee('reference.updated');
    }

    /** TC-FT-30: the first administrator comes only from the environment. */
    #[Test]
    public function the_admin_seeder_refuses_to_run_without_environment_values_and_creates_the_admin_otherwise(): void
    {
        config(['admin.name' => 'First Admin', 'admin.email' => 'first@example.test', 'admin.initial_password' => '']);

        try {
            $this->seed(AdminSeeder::class);
            $this->fail('The seeder ran without a password.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('ADMIN_INITIAL_PASSWORD', $exception->getMessage());
        }

        config(['admin.initial_password' => 'a-long-initial-password']);
        $this->seed(AdminSeeder::class);
        $this->seed(AdminSeeder::class);

        $admin = User::query()->where('email', 'first@example.test')->sole();
        $this->assertSame(Role::Admin, $admin->role);
        $this->assertTrue($admin->must_change_password);
    }

    /** TC-FT-31 */
    #[Test]
    public function the_console_command_creates_an_officer_with_a_hidden_password(): void
    {
        $station = Station::factory()->create(['name' => 'Alpha Sample Station']);

        $command = $this->artisan('user:create');
        if (! $command instanceof PendingCommand) {
            $this->fail('Console output mocking is off.');
        }

        $command
            ->expectsQuestion('Full name', 'Console Officer')
            ->expectsQuestion('Email', 'console@example.test')
            ->expectsChoice('Role', 'officer', ['admin', 'officer', 'analyst'])
            ->expectsChoice('Station', 'Alpha Sample Station', ['Alpha Sample Station'])
            ->expectsQuestion('Password', 'console-pass-123')
            ->expectsQuestion('Confirm password', 'console-pass-123')
            ->assertExitCode(0);
        $command->run();

        $user = User::query()->where('email', 'console@example.test')->sole();
        $this->assertSame($station->id, $user->station_id);
        $this->assertTrue($user->must_change_password);
    }
}
