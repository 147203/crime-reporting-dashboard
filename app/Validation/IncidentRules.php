<?php

declare(strict_types=1);

namespace App\Validation;

use App\Enums\AgeBand;
use App\Enums\InjuryLevel;
use App\Enums\OffenderRelationship;
use App\Enums\VictimSex;
use App\Enums\VictimType;
use App\Models\Incident;
use App\Rules\NoPersonalIdentifiers;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;

/**
 * Validation rules V1 to V7 and V9 (docs/02 section 5), shared by the incident form and the CSV import so both enforce the
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
            ...self::victimRules(),
        ];
    }

    public const VICTIM_COUNT_MAX = 50;

    public const VICTIM_AGE_MAX = 120;

    /**
     * V9: the victim profile holds descriptive values from fixed lists only, never names, contacts or ID numbers.
     *
     * @return array<string, list<mixed>>
     */
    public static function victimRules(): array
    {
        return [
            'victim_type' => ['required', Rule::enum(VictimType::class)],
            'victim_count' => ['nullable', 'integer', 'min:0', 'max:'.self::VICTIM_COUNT_MAX, Rule::when(fn ($input): bool => ($input['victim_type'] ?? null) === VictimType::Individual->value, ['min:1'])],
            'victim_sex' => ['required', Rule::enum(VictimSex::class)],
            'victim_age' => ['nullable', 'integer', 'min:0', 'max:'.self::VICTIM_AGE_MAX],
            'victim_age_band' => ['required', Rule::enum(AgeBand::class)],
            'victim_injury' => ['required', Rule::enum(InjuryLevel::class)],
            'offender_relationship' => ['required', Rule::enum(OffenderRelationship::class)],
        ];
    }

    /**
     * Fills unrecorded victim values, derives the age band from an exact age, and clears the person details when the
     * victim is not an individual, so the stored profile is always consistent whatever the input.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normaliseVictim(array $input): array
    {
        // Anything that is not a string (an array, say) is passed on unchanged, so the list rules reject it.
        $text = fn (string $key, string $default): mixed => match (true) {
            ! array_key_exists($key, $input) || $input[$key] === null => $default,
            ! is_string($input[$key]) => $input[$key],
            trim($input[$key]) === '' => $default,
            default => strtolower(trim($input[$key])),
        };
        $number = fn (string $key): mixed => is_string($input[$key] ?? null) ? (trim($input[$key]) === '' ? null : trim($input[$key])) : ($input[$key] ?? null);

        $victim = [
            'victim_type' => $text('victim_type', VictimType::NotRecorded->value),
            'victim_count' => $number('victim_count'),
            'victim_sex' => $text('victim_sex', VictimSex::NotRecorded->value),
            'victim_age' => $number('victim_age'),
            'victim_age_band' => $text('victim_age_band', AgeBand::NotRecorded->value),
            'victim_injury' => $text('victim_injury', InjuryLevel::NotRecorded->value),
            'offender_relationship' => $text('offender_relationship', OffenderRelationship::NotKnown->value),
        ];

        if (is_numeric($victim['victim_age']) && (int) $victim['victim_age'] == $victim['victim_age'] && (int) $victim['victim_age'] >= 0) {
            $victim['victim_age_band'] = AgeBand::fromAge((int) $victim['victim_age'])->value;
        }

        if (is_string($victim['victim_type']) && in_array($victim['victim_type'], [VictimType::Business->value, VictimType::PublicProperty->value], true)) {
            $victim['victim_sex'] = VictimSex::NotRecorded->value;
            $victim['victim_age'] = null;
            $victim['victim_age_band'] = AgeBand::NotRecorded->value;
            $victim['offender_relationship'] = OffenderRelationship::NotKnown->value;
        }

        return $victim;
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
            'victim_count.max' => 'The number of victims cannot be more than '.self::VICTIM_COUNT_MAX.'.',
            'victim_count.min' => 'An individual victim means at least 1 victim; leave the number empty if it is not known.',
            'victim_age.max' => 'The age cannot be more than '.self::VICTIM_AGE_MAX.' years.',
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
            'victim_type' => 'victim type',
            'victim_count' => 'number of victims',
            'victim_sex' => 'sex',
            'victim_age' => 'age',
            'victim_age_band' => 'age band',
            'victim_injury' => 'injury',
            'offender_relationship' => 'relationship to the suspect',
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
        // Repeated until nothing changes, so nested fragments such as "<scr<b>ipt>" cannot reassemble into a tag.
        do {
            $before = $value;
            $value = (string) preg_replace('/<\/?[a-z!][^<>]*>/i', '', $value);
        } while ($value !== $before);
        $clean = trim($value);

        return $clean === '' ? null : $clean;
    }
}
