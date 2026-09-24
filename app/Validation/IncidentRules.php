<?php

declare(strict_types=1);

namespace App\Validation;

use App\Models\Incident;
use App\Rules\NoPersonalIdentifiers;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;

/**
 * Validation rules V1 to V7 (docs/02 section 5), shared by the incident form and the CSV import so both enforce the
 * same rules. V8 (duplicates) is a review flag, not a rejection, and runs in the service.
 */
final class IncidentRules
{
    public const LOCATION_MAX = 150;

    public const DESCRIPTION_MAX = 1000;

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(mixed $subCountyId, ?Incident $current = null): array
    {
        $now = now();
        // An existing record may keep a category or station that has since been deactivated; new choices must be active.
        $activeOrCurrent = fn (?int $currentId) => fn (Builder $query) => $query->where(fn (Builder $q) => $q->where('is_active', true)->orWhere('id', $currentId ?? 0));

        return [
            'category_id' => ['required', 'integer', Rule::exists('crime_categories', 'id')->where($activeOrCurrent($current?->category_id))],
            'occurred_at' => ['required', 'date', 'before_or_equal:'.$now->toDateTimeString(), 'after_or_equal:'.$now->copy()->subYears(10)->toDateTimeString()],
            'reported_at' => ['required', 'date', 'after_or_equal:occurred_at', 'before_or_equal:'.$now->toDateTimeString()],
            'sub_county_id' => ['required', 'integer', Rule::exists('sub_counties', 'id')],
            'ward_id' => ['required', 'integer', Rule::exists('wards', 'id')->where('sub_county_id', is_numeric($subCountyId) ? (int) $subCountyId : 0)],
            'station_id' => ['required', 'integer', Rule::exists('stations', 'id')->where($activeOrCurrent($current?->station_id))],
            'location_description' => ['nullable', 'string', 'max:'.self::LOCATION_MAX, new NoPersonalIdentifiers],
            'description' => ['nullable', 'string', 'max:'.self::DESCRIPTION_MAX, new NoPersonalIdentifiers],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'category_id.exists' => 'Choose an active crime category.',
            'occurred_at.before_or_equal' => 'The date and time of the incident cannot be in the future.',
            'occurred_at.after_or_equal' => 'The incident date cannot be more than 10 years ago.',
            'reported_at.after_or_equal' => 'The reported time cannot be before the incident occurred.',
            'reported_at.before_or_equal' => 'The reported time cannot be in the future.',
            'ward_id.exists' => 'The ward must belong to the chosen sub-county.',
            'station_id.exists' => 'Choose an active station.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return [
            'category_id' => 'category',
            'occurred_at' => 'date and time occurred',
            'reported_at' => 'date and time reported',
            'sub_county_id' => 'sub-county',
            'ward_id' => 'ward',
            'station_id' => 'station',
            'location_description' => 'street or landmark',
            'description' => 'description',
        ];
    }

    /**
     * V5: HTML is stripped before validation and storage.
     */
    public static function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // Removes tags such as <b> or </p> but keeps a lone "<" (strip_tags would cut the rest of the text).
        $clean = trim((string) preg_replace('/<\/?[a-z!][^<>]*>/i', '', $value));

        return $clean === '' ? null : $clean;
    }
}
