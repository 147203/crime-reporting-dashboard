<?php

declare(strict_types=1);

namespace App\Rules;

use App\Services\PiiGuard;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class NoPersonalIdentifiers implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $findings = app(PiiGuard::class)->findings(is_string($value) ? $value : null);

        if ($findings !== []) {
            $fail('The :attribute appears to contain a '.implode(' and a ', $findings).'. Personal identifiers must not be recorded; describe the incident without them.');
        }
    }
}
