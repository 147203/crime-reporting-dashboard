<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\DuplicateStatus;
use App\Enums\IncidentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Every filter parameter is validated before it reaches a query (project rule).
 */
final class IncidentIndexRequest extends FormRequest
{
    public const SORTABLE = ['occurred_at', 'reported_at', 'reference_no', 'status'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:60'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'category_id' => ['nullable', 'integer', Rule::exists('crime_categories', 'id')],
            'sub_county_id' => ['nullable', 'integer', Rule::exists('sub_counties', 'id')],
            'ward_id' => ['nullable', 'integer', Rule::exists('wards', 'id')],
            'station_id' => ['nullable', 'integer', Rule::exists('stations', 'id')],
            'status' => ['nullable', Rule::enum(IncidentStatus::class)],
            'duplicate_status' => ['nullable', Rule::enum(DuplicateStatus::class)],
            'weekday' => ['nullable', 'integer', 'between:1,7'],
            'band' => ['nullable', 'integer', 'between:0,'.(count(config('analytics.time_bands')) - 1)],
            'countable' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(self::SORTABLE)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }
}
