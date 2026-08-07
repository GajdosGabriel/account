<?php

namespace App\Rules;

use App\Services\Registry\ViesValidator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidVatNumber implements ValidationRule
{
    public function __construct(private readonly bool $checkVies = false) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        $clean = strtoupper(preg_replace('/\s+/', '', (string) $value) ?? '');

        if (! preg_match('/^[A-Z]{2}[A-Z0-9]{2,13}$/', $clean)) {
            $fail('validation.vat_format')->translate();

            return;
        }

        if (! in_array(substr($clean, 0, 2), ViesValidator::EU_COUNTRIES, true)) {
            $fail('validation.vat_country')->translate();

            return;
        }

        if ($this->checkVies) {
            $result = app(ViesValidator::class)->validate($clean);

            // Keď VIES nebeží, formulár nezablokujeme – overí sa neskôr.
            if ($result['checked'] && ! $result['valid']) {
                $fail('validation.vat_vies')->translate();
            }
        }
    }
}
