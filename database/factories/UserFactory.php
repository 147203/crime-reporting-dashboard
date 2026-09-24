<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Station;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('factory-password-for-tests'),
            'remember_token' => Str::random(10),
            'role' => Role::Analyst,
            'is_active' => true,
            'must_change_password' => false,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => Role::Admin, 'station_id' => null]);
    }

    public function analyst(): static
    {
        return $this->state(fn () => ['role' => Role::Analyst, 'station_id' => null]);
    }

    public function officer(?Station $station = null): static
    {
        return $this->state(fn () => ['role' => Role::Officer, 'station_id' => $station->id ?? Station::factory()]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
