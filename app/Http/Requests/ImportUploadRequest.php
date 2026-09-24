<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\Import\ImportService;
use Illuminate\Foundation\Http\FormRequest;

final class ImportUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['file' => ['required', 'file', 'mimes:csv,txt', 'max:'.ImportService::MAX_KILOBYTES]];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['file.max' => 'The file may not be larger than 5 MB.', 'file.mimes' => 'Upload a CSV file.'];
    }
}
