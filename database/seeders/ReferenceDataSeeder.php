<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CrimeCategory;
use App\Models\Station;
use App\Models\SubCounty;
use App\Models\Ward;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Loads sub-counties, wards, sample stations and crime categories from database/seeders/data. Safe to run repeatedly.
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->rows('sub_counties.csv') as $row) {
            SubCounty::query()->firstOrCreate(['name' => $row['name']]);
        }

        $subCounties = SubCounty::query()->pluck('id', 'name');

        foreach ($this->rows('wards.csv') as $row) {
            Ward::query()->firstOrCreate(['sub_county_id' => $this->idFor($subCounties->all(), $row['sub_county']), 'name' => $row['name']]);
        }

        foreach ($this->rows('stations.csv') as $row) {
            Station::query()->firstOrCreate(['name' => $row['name']], ['sub_county_id' => $this->idFor($subCounties->all(), $row['sub_county'])]);
        }

        foreach ($this->rows('crime_categories.csv') as $row) {
            CrimeCategory::query()->firstOrCreate(['name' => $row['name']]);
        }
    }

    /**
     * @return list<array<string, string>>
     */
    private function rows(string $file): array
    {
        $lines = array_values(array_filter(
            file(database_path("seeders/data/{$file}"), FILE_IGNORE_NEW_LINES) ?: [],
            fn (string $line): bool => $line !== '' && ! str_starts_with($line, '#'),
        ));

        $header = $this->fields(array_shift($lines) ?? '');

        return array_map(fn (string $line): array => array_combine($header, $this->fields($line)), $lines);
    }

    /**
     * @return list<string>
     */
    private function fields(string $line): array
    {
        return array_map(fn (?string $field): string => $field ?? '', str_getcsv($line, escape: ''));
    }

    /**
     * @param  array<string, int>  $ids
     */
    private function idFor(array $ids, string $name): int
    {
        return $ids[$name] ?? throw new RuntimeException("Unknown sub-county in reference data: {$name}");
    }
}
