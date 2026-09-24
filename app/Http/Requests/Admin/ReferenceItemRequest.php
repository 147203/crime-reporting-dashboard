<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Incident;
use App\Models\Ward;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One request for the four reference lists; the route name selects the rules.
 */
final class ReferenceItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $item = $this->route('item');
        $ignore = is_numeric($item) ? (int) $item : null;
        $type = (string) $this->route('type');

        return match ($type) {
            'categories' => [
                'name' => ['required', 'string', 'max:60', Rule::unique('crime_categories', 'name')->ignore($ignore)],
                'is_active' => ['sometimes', 'boolean'],
            ],
            'sub-counties' => [
                'name' => ['required', 'string', 'max:80', Rule::unique('sub_counties', 'name')->ignore($ignore)],
            ],
            'wards' => [
                'sub_county_id' => ['required', 'integer', Rule::exists('sub_counties', 'id'), function (string $attribute, mixed $value, \Closure $fail) use ($ignore): void {
                    $ward = $ignore !== null ? Ward::query()->find($ignore) : null;
                    if ($ward !== null && $ward->sub_county_id !== (int) $value && Incident::withTrashed()->where('ward_id', $ward->id)->exists()) {
                        $fail('This ward already has incidents, so it cannot move to another sub-county. Add a new ward instead.');
                    }
                }],
                'name' => ['required', 'string', 'max:80', Rule::unique('wards', 'name')->where('sub_county_id', (int) $this->input('sub_county_id'))->ignore($ignore)],
            ],
            'stations' => [
                'sub_county_id' => ['required', 'integer', Rule::exists('sub_counties', 'id')],
                'name' => ['required', 'string', 'max:120', Rule::unique('stations', 'name')->ignore($ignore)],
                'is_active' => ['sometimes', 'boolean'],
            ],
            default => abort(404),
        };
    }
}
