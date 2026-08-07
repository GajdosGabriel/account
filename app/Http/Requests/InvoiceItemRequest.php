<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InvoiceItemRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:255'],
            'detail' => ['nullable', 'string', 'max:500'],
            // Záporné množstvo je legitímne – tak sa robia zľavové riadky.
            'quantity' => ['required', 'numeric', 'between:-999999,999999', 'not_in:0'],
            'unit' => ['nullable', 'string', 'max:20'],
            // V eurách; na stotiny centa sa prepočíta v controlleri.
            'unit_price' => ['required', 'numeric', 'between:-999999,999999'],
            'discount_percent' => ['nullable', 'numeric', 'between:0,100'],
            'vat_rate' => ['nullable', 'numeric', 'between:0,100'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'plan_id' => ['nullable', 'integer', 'exists:plans,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quantity.not_in' => 'Množstvo nemôže byť nula.',
            'period_end.after_or_equal' => 'Koniec obdobia nemôže byť skôr než jeho začiatok.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'description' => 'popis',
            'quantity' => 'množstvo',
            'unit_price' => 'jednotková cena',
            'vat_rate' => 'sadzba DPH',
        ];
    }
}
