<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\CrimeCategory;
use App\Models\Station;
use App\Models\SubCounty;
use App\Models\Ward;

/**
 * Option lists for forms and filter bars.
 */
final class ReferenceOptions
{
    /**
     * @return array<int, string>
     */
    public function categories(bool $activeOnly = false, ?int $keep = null): array
    {
        return CrimeCategory::query()->when($activeOnly, fn ($q) => $q->where(fn ($inner) => $inner->where('is_active', true)->orWhere('id', $keep ?? 0)))->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * @return array<int, string>
     */
    public function subCounties(): array
    {
        return SubCounty::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * @return list<array{value: int, label: string, group: int}>
     */
    public function wards(): array
    {
        return array_values(Ward::query()->orderBy('name')->get(['id', 'name', 'sub_county_id'])
            ->map(fn (Ward $ward): array => ['value' => $ward->id, 'label' => $ward->name, 'group' => $ward->sub_county_id])
            ->all());
    }

    /**
     * @return array<int, string>
     */
    public function stations(bool $activeOnly = false, ?int $keep = null): array
    {
        return Station::query()->when($activeOnly, fn ($q) => $q->where(fn ($inner) => $inner->where('is_active', true)->orWhere('id', $keep ?? 0)))->orderBy('name')->pluck('name', 'id')->all();
    }
}
