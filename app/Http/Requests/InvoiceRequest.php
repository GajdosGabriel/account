<?php

namespace App\Http\Requests;

use App\Enums\InvoiceType;
use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvoiceRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'organization_id' => [Rule::requiredIf($this->isMethod('post')), 'string'],
            'type' => ['sometimes', Rule::enum(InvoiceType::class)],
            'payment_method' => ['sometimes', Rule::enum(PaymentMethod::class)],

            'issued_at' => ['nullable', 'date'],
            'delivered_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', 'after_or_equal:issued_at'],

            'variable_symbol' => ['nullable', 'string', 'max:10', 'regex:/^\d*$/'],
            'constant_symbol' => ['nullable', 'string', 'max:4', 'regex:/^\d*$/'],
            'specific_symbol' => ['nullable', 'string', 'max:10', 'regex:/^\d*$/'],

            'currency' => ['sometimes', 'string', 'size:3'],
            'locale' => ['sometimes', 'string', Rule::in(config('accounts.locales'))],
            'rounding_cents' => ['nullable', 'integer', 'between:-99,99'],

            'note' => ['nullable', 'string', 'max:2000'],
            'internal_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'due_at.after_or_equal' => 'Splatnosť nemôže byť skôr než dátum vystavenia.',
            'variable_symbol.regex' => 'Variabilný symbol môže obsahovať len číslice.',
            'constant_symbol.regex' => 'Konštantný symbol môže obsahovať len číslice.',
            'specific_symbol.regex' => 'Špecifický symbol môže obsahovať len číslice.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'organization_id' => 'organizácia',
            'issued_at' => 'dátum vystavenia',
            'delivered_at' => 'dátum dodania',
            'due_at' => 'dátum splatnosti',
        ];
    }
}
