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
            'due_at.after_or_equal' => __('flash.validation.due_at'),
            'variable_symbol.regex' => __('flash.validation.symbol_digits', ['attribute' => __('flash.validation.attributes.variable_symbol')]),
            'constant_symbol.regex' => __('flash.validation.symbol_digits', ['attribute' => __('flash.validation.attributes.constant_symbol')]),
            'specific_symbol.regex' => __('flash.validation.symbol_digits', ['attribute' => __('flash.validation.attributes.specific_symbol')]),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'organization_id' => __('flash.validation.attributes.organization_id'),
            'issued_at' => __('flash.validation.attributes.issued_at'),
            'delivered_at' => __('flash.validation.attributes.delivered_at'),
            'due_at' => __('flash.validation.attributes.due_at'),
        ];
    }
}
