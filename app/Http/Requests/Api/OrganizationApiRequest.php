<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\Concerns\OrganizationRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validácia firemných údajov prichádzajúcich z formulára projektu.
 *
 * Pravidlá sú zámerne tu, nie v projektoch – IČO a IČ DPH sa tak
 * kontrolujú raz a všetky projekty sa správajú rovnako.
 */
class OrganizationApiRequest extends FormRequest
{
    use OrganizationRules;

    public function authorize(): bool
    {
        return true; // rieši middleware `service`
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $organization = $this->route('organization');

        return $this->organizationRules(
            ignoreId: $organization?->id,
            partial: $organization !== null,
            // pri zakladaní sa na existujúcu firmu iba naviažeme (viď controller),
            // pri úprave konkrétnej firmy unikátnosť platí
            uniqueIco: $organization !== null,
        ) + [
            'external_ref' => ['nullable', 'string', 'max:120'],
        ];
    }


    protected function prepareForValidation(): void
    {
        $this->merge($this->normalizedOrganizationInput());
    }

    /**
     * Údaje na uloženie – bez `external_ref`, ktorý patrí do väzobnej tabuľky.
     *
     * @return array<string, mixed>
     */
    public function organizationData(): array
    {
        return collect($this->validated())->except('external_ref')->all();
    }
}
