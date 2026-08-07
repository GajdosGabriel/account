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


    protected function prepareForValidation(): void
    {
        $this->merge($this->normalizedOrganizationInput());
    }
}
