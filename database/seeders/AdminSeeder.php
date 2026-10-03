<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use App\Support\Preflight;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Creates the first administrator from ADMIN_NAME, ADMIN_EMAIL and ADMIN_INITIAL_PASSWORD. There is no default; it
 * refuses to run when any is missing or the password fails preflight. It never changes an existing account.
 */
class AdminSeeder extends Seeder
{
    public function run(Preflight $preflight): void
    {
        $name = (string) config('admin.name');
        $email = mb_strtolower(trim((string) config('admin.email')));
        $failures = $preflight->adminPasswordFailures();

        if ($name === '' || $email === '' || $failures !== []) {
            throw new RuntimeException('Cannot create the first administrator: set ADMIN_NAME, ADMIN_EMAIL and a valid ADMIN_INITIAL_PASSWORD in .env. '.implode(' ', $failures));
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->command->info("Administrator {$email} already exists; left unchanged.");

            return;
        }

        $admin = new User(['name' => $name, 'email' => $email, 'password' => (string) config('admin.initial_password')]);
        $admin->role = Role::Admin;
        $admin->is_active = true;
        $admin->must_change_password = true;
        $admin->save();

        $this->command->info("Administrator {$email} created. The password must be changed at first login, after which ADMIN_INITIAL_PASSWORD no longer signs in.");
    }
}
