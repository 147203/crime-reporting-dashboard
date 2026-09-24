<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SubCounty;
use App\Models\Ward;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ward>
 */
class WardFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sub_county_id' => SubCounty::factory(),
            'name' => 'Ward '.fake()->unique()->numerify('###'),
        ];
    }
}
