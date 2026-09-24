<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Create and edit a user. On edit the password is optional: setting one is an administrator reset and forces a change
 * at next login.
 */
final class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => mb_strtolower(trim($this->string('email')->toString()))]);

        if ($this->input('role') !== Role::Officer->value) {
            $this->merge(['station_id' => null]);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $user = $this->route('user');
        $isCreate = ! $user instanceof User;

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190', Rule::unique('users', 'email')->ignore($isCreate ? null : $user->id)],
            'role' => ['required', Rule::enum(Role::class)],
            'station_id' => ['nullable', 'required_if:role,'.Role::Officer->value, 'integer', Rule::exists('stations', 'id')->where('is_active', true)],
            'is_active' => ['sometimes', 'boolean'],
            'password' => [$isCreate ? 'required' : 'nullable', 'string', 'confirmed', Password::min((int) config('security.password_min_length'))->letters()->numbers()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['station_id.required_if' => 'A Reporting Officer must be assigned to a station.'];
    }
}
