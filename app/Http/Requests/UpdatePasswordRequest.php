<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class UpdatePasswordRequest extends FormRequest
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
        return [
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', Password::min((int) config('security.password_min_length'))->letters()->numbers()],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        $user = $this->user();
        if ($user instanceof User && array_key_exists('current_password', $validator->failed())) {
            app(AuditLogger::class)->record('auth.password_change_failed', $user, ['reason' => 'wrong_current_password'], $user);
        }

        parent::failedValidation($validator);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['current_password.current_password' => 'The current password is not correct.'];
    }
}
