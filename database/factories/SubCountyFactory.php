<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SubCounty;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubCounty>
 */
class SubCountyFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => 'Sub-county '.fake()->unique()->numerify('###')];
    }
}
