<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\IncidentStatus;
use App\Models\User;
use App\Support\Filters;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filters for the dashboard and reports. The period defaults to the last 12 months including the current one. Officers
 * are already limited to their station by scope, so a station filter from them is ignored.
 */
final class AnalyticsFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'from' => $this->input('from') ?: now()->subMonthsNoOverflow(11)->startOfMonth()->toDateString(),
            'to' => $this->input('to') ?: now()->toDateString(),
        ]);

        $user = $this->user();
        if ($user instanceof User && $user->role->isStationScoped()) {
            $this->merge(['station_id' => null]);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:'.now()->addYear()->toDateString()],
            'category_id' => ['nullable', 'integer', Rule::exists('crime_categories', 'id')],
            'sub_county_id' => ['nullable', 'integer', Rule::exists('sub_counties', 'id')],
            'ward_id' => ['nullable', 'integer', Rule::exists('wards', 'id')],
            'station_id' => ['nullable', 'integer', Rule::exists('stations', 'id')],
            'status' => ['nullable', Rule::enum(IncidentStatus::class)],
        ];
    }

    public function filters(): Filters
    {
        return Filters::fromValidated($this->validated());
    }
}
