<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CrimeCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CrimeCategory>
 */
class CrimeCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Category '.fake()->unique()->numerify('###'),
            'is_active' => true,
        ];
    }
}
