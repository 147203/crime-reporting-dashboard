<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\Station;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class CreateUserCommand extends Command
{
    protected $signature = 'user:create';

    protected $description = 'Create a user account interactively (the password is typed hidden and must be changed at first login)';

    public function handle(AuditLogger $audit): int
    {
        $data = [
            'name' => $this->text($this->ask('Full name')),
            'email' => mb_strtolower(trim($this->text($this->ask('Email')))),
            'role' => $this->text($this->choice('Role', array_map(fn (Role $role): string => $role->value, Role::cases()), Role::Officer->value)),
        ];

        if ($data['role'] === Role::Officer->value) {
            $stations = Station::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all();
            $chosen = $this->text($this->choice('Station', array_values($stations)));
            $data['station_id'] = array_search($chosen, $stations, true);
        }

        $data['password'] = $this->text($this->secret('Password'));
        $data['password_confirmation'] = $this->text($this->secret('Confirm password'));

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190', Rule::unique('users', 'email')],
            'role' => ['required', Rule::enum(Role::class)],
            'station_id' => ['nullable', 'required_if:role,officer', 'integer', Rule::exists('stations', 'id')],
            'password' => ['required', 'confirmed', Password::min((int) config('security.password_min_length'))->letters()->numbers()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = new User(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
        $user->role = Role::from($data['role']);
        $user->station_id = isset($data['station_id']) ? (int) $data['station_id'] : null;
        $user->must_change_password = true;
        $user->save();
        $audit->record('user.created', $user, ['role' => $user->role->value, 'via' => 'console']);

        $this->info("Created {$user->role->label()} {$user->email}. They must change the password at first login.");

        return self::SUCCESS;
    }

    private function text(mixed $answer): string
    {
        return is_string($answer) ? $answer : '';
    }
}
