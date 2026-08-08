<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\OrganizationRules;
use Illuminate\Foundation\Http\FormRequest;

class OrganizationRequest extends FormRequest
{
    use OrganizationRules;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->organizationRules($this->route('organization')?->id) + [
            'status' => ['required', 'in:active,suspended,archived'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Údaje na uloženie. Rovnako ako v API sa nevyplnené polia s databázovým
     * defaultom vyhadzujú – prázdny select v admin formulári by inak
     * skončil na integrity violation.
     *
     * @return array<string, mixed>
     */
    public function organizationData(): array
    {
        return $this->withoutNullDefaults($this->validated());
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->normalizedOrganizationInput());
    }
}
