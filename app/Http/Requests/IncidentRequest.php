<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Incident;
use App\Models\User;
use App\Validation\IncidentRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Create and edit share the same rules. Officers always record against their own station, whatever the input says.
 */
final class IncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $user = $this->user();

        $this->merge([
            'location_description' => IncidentRules::clean($this->string('location_description')->toString()),
            'description' => IncidentRules::clean($this->string('description')->toString()),
        ]);

        if ($user instanceof User && $user->role->isStationScoped()) {
            $this->merge(['station_id' => $user->station_id]);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $incident = $this->route('incident');

        return IncidentRules::rules($this->input('sub_county_id'), $incident instanceof Incident ? $incident : null);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return IncidentRules::messages();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return IncidentRules::attributes();
    }
}
