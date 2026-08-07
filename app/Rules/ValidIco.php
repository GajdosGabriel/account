<?php

namespace App\Rules;

use App\Services\Registry\IcoLookupService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidIco implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        if (! app(IcoLookupService::class)->isValidChecksum((string) $value)) {
            $fail('validation.ico_checksum')->translate();
        }
    }
}
