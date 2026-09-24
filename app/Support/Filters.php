<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Incident;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The standard filter set (docs/02 section 4): period on occurred_at, category, sub-county, ward, station, status.
 * Built only from validated input.
 */
final readonly class Filters
{
    public function __construct(
        public ?CarbonImmutable $from = null,
        public ?CarbonImmutable $to = null,
        public ?int $categoryId = null,
        public ?int $subCountyId = null,
        public ?int $wardId = null,
        public ?int $stationId = null,
        public ?string $status = null,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        $date = fn (string $key): ?CarbonImmutable => isset($validated[$key]) && is_string($validated[$key]) ? CarbonImmutable::parse($validated[$key]) : null;
        $id = fn (string $key): ?int => isset($validated[$key]) && is_numeric($validated[$key]) ? (int) $validated[$key] : null;

        return new self(
            from: $date('from')?->startOfDay(),
            to: $date('to')?->endOfDay(),
            categoryId: $id('category_id'),
            subCountyId: $id('sub_county_id'),
            wardId: $id('ward_id'),
            stationId: $id('station_id'),
            status: isset($validated['status']) && is_string($validated['status']) ? $validated['status'] : null,
        );
    }

    /**
     * @param  Builder<Incident>  $query
     * @return Builder<Incident>
     */
    public function apply(Builder $query): Builder
    {
        return $query
            ->when($this->from, fn (Builder $q, CarbonImmutable $from) => $q->where('incidents.occurred_at', '>=', $from))
            ->when($this->to, fn (Builder $q, CarbonImmutable $to) => $q->where('incidents.occurred_at', '<=', $to))
            ->when($this->categoryId, fn (Builder $q, int $id) => $q->where('incidents.category_id', $id))
            ->when($this->subCountyId, fn (Builder $q, int $id) => $q->where('incidents.sub_county_id', $id))
            ->when($this->wardId, fn (Builder $q, int $id) => $q->where('incidents.ward_id', $id))
            ->when($this->stationId, fn (Builder $q, int $id) => $q->where('incidents.station_id', $id))
            ->when($this->status, fn (Builder $q, string $status) => $q->where('incidents.status', $status));
    }

    /**
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return array_filter([
            'from' => $this->from?->toDateString(),
            'to' => $this->to?->toDateString(),
            'category_id' => $this->categoryId,
            'sub_county_id' => $this->subCountyId,
            'ward_id' => $this->wardId,
            'station_id' => $this->stationId,
            'status' => $this->status,
        ], fn (mixed $value): bool => $value !== null);
    }
}
