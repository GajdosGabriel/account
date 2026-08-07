<?php

namespace App\Http\Requests\Concerns;

use App\Enums\LegalForm;
use App\Enums\VatMode;
use App\Rules\ValidIco;
use App\Rules\ValidVatNumber;
use Illuminate\Validation\Rule;

/**
 * Pravidlá pre firemné údaje na jednom mieste – používa ich admin formulár
 * aj API, ktorým zapisujú projekty. Vďaka tomu sa všetky správajú rovnako.
 */
trait OrganizationRules
{
    /**
     * @return array<string, mixed>
     */
    protected function organizationRules(?int $ignoreId = null, bool $partial = false, bool $uniqueIco = true): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            /* identifikácia */
            'name' => [$required, 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'legal_form' => ['nullable', Rule::enum(LegalForm::class)],
            'ico' => array_filter([
                'nullable', 'string', 'max:12', new ValidIco,
                // Projekty duplikát neposielajú omylom – existujúcu firmu im
                // controller naviaže. Unikátnosť preto vynucujeme len tam,
                // kde ju zakladá človek.
                $uniqueIco
                    ? Rule::unique('organizations', 'ico')->ignore($ignoreId)->whereNull('deleted_at')
                    : null,
            ]),
            'dic' => ['nullable', 'string', 'max:15'],
            'ic_dph' => ['nullable', 'string', 'max:15', new ValidVatNumber(checkVies: true)],
            'vat_mode' => ['nullable', Rule::enum(VatMode::class)],
            'oss_registered' => ['boolean'],

            /* zápis v registri */
            'register_court' => ['nullable', 'string', 'max:255'],
            'register_section' => ['nullable', 'string', 'max:20'],
            'register_insert' => ['nullable', 'string', 'max:30'],
            'established_at' => ['nullable', 'date', 'before_or_equal:today'],

            /* sídlo */
            'street' => ['nullable', 'string', 'max:255'],
            'street_no' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:12'],
            'region' => ['nullable', 'string', 'max:80'],
            'country' => ['nullable', 'string', 'size:2'],

            /* kontakt */
            'email' => ['nullable', 'email', 'max:255'],
            'billing_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'url', 'max:255'],

            /* banka */
            'bank_name' => ['nullable', 'string', 'max:120'],
            'iban' => ['nullable', 'string', 'max:34'],
            'swift' => ['nullable', 'string', 'max:11'],

            /* fakturačné preferencie */
            'currency' => ['nullable', 'string', 'size:3'],
            'payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:180'],
            'payment_method' => ['nullable', 'in:transfer,card,cash,cod'],
            'invoice_language' => ['nullable', 'string', 'size:2'],
            'invoice_delivery' => ['nullable', 'in:email,post,both'],
            'supplier_number' => ['nullable', 'string', 'max:40'],
        ];
    }

    /**
     * Normalizácia pred validáciou – medzery v IČO, veľké písmená v kódoch.
     *
     * @return array<string, mixed>
     */
    protected function normalizedOrganizationInput(): array
    {
        $merge = [];

        foreach (['ico', 'iban', 'swift', 'ic_dph'] as $field) {
            if ($this->filled($field)) {
                $value = preg_replace('/\s+/', '', $this->string($field)->toString());
                $merge[$field] = $field === 'ico' ? $value : strtoupper($value);
            }
        }

        foreach (['country', 'currency'] as $field) {
            if ($this->filled($field)) {
                $merge[$field] = strtoupper($this->string($field)->toString());
            }
        }

        return $merge;
    }
}
