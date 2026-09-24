<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Models\CrimeCategory;
use App\Models\Station;
use App\Models\SubCounty;
use App\Models\Ward;

/**
 * Name to id maps for the import, loaded once per file so each row costs no reference queries. Matching is
 * case-insensitive and ignores surrounding spaces.
 */
final class ReferenceLookup
{
    /** @var array<string, array{id: int, active: bool}> */
    private array $categories = [];

    /** @var array<string, int> */
    private array $subCounties = [];

    /** @var array<int, array<string, int>> sub-county id => ward name => ward id */
    private array $wards = [];

    /** @var array<string, array{id: int, active: bool}> */
    private array $stations = [];

    public function __construct()
    {
        foreach (CrimeCategory::query()->get(['id', 'name', 'is_active']) as $category) {
            $this->categories[self::key($category->name)] = ['id' => $category->id, 'active' => $category->is_active];
        }
        foreach (SubCounty::query()->get(['id', 'name']) as $subCounty) {
            $this->subCounties[self::key($subCounty->name)] = $subCounty->id;
        }
        foreach (Ward::query()->get(['id', 'name', 'sub_county_id']) as $ward) {
            $this->wards[$ward->sub_county_id][self::key($ward->name)] = $ward->id;
        }
        foreach (Station::query()->get(['id', 'name', 'is_active']) as $station) {
            $this->stations[self::key($station->name)] = ['id' => $station->id, 'active' => $station->is_active];
        }
    }

    public function activeCategory(string $name): ?int
    {
        $category = $this->categories[self::key($name)] ?? null;

        return $category !== null && $category['active'] ? $category['id'] : null;
    }

    public function subCounty(string $name): ?int
    {
        return $this->subCounties[self::key($name)] ?? null;
    }

    public function ward(int $subCountyId, string $name): ?int
    {
        return $this->wards[$subCountyId][self::key($name)] ?? null;
    }

    public function wardInAnySubCounty(string $name): bool
    {
        foreach ($this->wards as $wards) {
            if (isset($wards[self::key($name)])) {
                return true;
            }
        }

        return false;
    }

    public function activeStation(string $name): ?int
    {
        $station = $this->stations[self::key($name)] ?? null;

        return $station !== null && $station['active'] ? $station['id'] : null;
    }

    private static function key(string $name): string
    {
        return mb_strtolower(trim($name));
    }
}
