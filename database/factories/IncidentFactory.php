<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DuplicateStatus;
use App\Enums\IncidentStatus;
use App\Models\CrimeCategory;
use App\Models\Incident;
use App\Models\Station;
use App\Models\Ward;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Incident>
 */
class IncidentFactory extends Factory
{
    public function definition(): array
    {
        $occurred = fake()->dateTimeBetween('-11 months', '-2 days');

        return [
            'category_id' => CrimeCategory::factory(),
            'occurred_at' => $occurred,
            'reported_at' => (clone $occurred)->modify('+'.fake()->numberBetween(0, 48).' hours'),
            'ward_id' => Ward::factory(),
            'sub_county_id' => fn (array $attributes) => Ward::query()->whereKey($attributes['ward_id'])->value('sub_county_id'),
            'station_id' => fn (array $attributes) => Station::factory()->create(['sub_county_id' => $attributes['sub_county_id']])->id,
            'location_description' => 'Near the market',
            'description' => 'Sample incident description.',
            'status' => IncidentStatus::Reported,
            'duplicate_status' => DuplicateStatus::None,
            'is_synthetic' => true,
        ];
    }

    public function atStation(Station $station): static
    {
        return $this->state(fn () => [
            'station_id' => $station->id,
            'ward_id' => Ward::factory()->state(['sub_county_id' => $station->sub_county_id]),
            'sub_county_id' => $station->sub_county_id,
        ]);
    }
}
