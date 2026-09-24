<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\Station;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Console\Command;

/**
 * Rebuilds the end-to-end test schema. Refuses to run unless the connected database name ends in "_e2e" and the
 * environment is not production. Test accounts get the password in E2E_PASSWORD, which the Playwright config generates
 * at random for each run and never stores.
 */
final class E2ePrepareCommand extends Command
{
    protected $signature = 'e2e:prepare';

    protected $description = 'Reset the *_e2e database and create per-run test accounts (Playwright only)';

    public function handle(): int
    {
        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");
        $password = (string) config('security.e2e_password');

        if (app()->isProduction() || $connection !== 'mysql' || ! str_ends_with($database, '_e2e')) {
            $this->error('Refusing: e2e:prepare only runs against a database whose name ends in _e2e, outside production.');

            return self::FAILURE;
        }

        if (strlen($password) < 20) {
            $this->error('Refusing: E2E_PASSWORD must be set by the Playwright config (20+ random characters).');

            return self::FAILURE;
        }

        $this->call('migrate:fresh');
        $this->call('db:seed', ['--class' => ReferenceDataSeeder::class]);

        $station = Station::query()->orderBy('id')->firstOrFail();

        foreach ([Role::Admin, Role::Officer, Role::Analyst] as $role) {
            $user = new User(['name' => "E2E {$role->label()}", 'email' => "{$role->value}@e2e.test", 'password' => $password]);
            $user->role = $role;
            $user->station_id = $role === Role::Officer ? $station->id : null;
            $user->must_change_password = false;
            $user->save();
        }

        if ($this->getApplication()?->has('sample:generate')) {
            $this->call('sample:generate', ['--size' => 2000, '--seed' => 20260924, '--load' => true]);
        }

        $this->info('E2E database ready.');

        return self::SUCCESS;
    }
}
