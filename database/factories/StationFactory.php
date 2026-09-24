<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Station;
use App\Models\SubCounty;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Station>
 */
class StationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sub_county_id' => SubCounty::factory(),
            'name' => 'Station '.fake()->unique()->numerify('###'),
            'is_active' => true,
        ];
    }
}
