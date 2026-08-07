<?php

namespace App\Services\Invoicing;

use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Services\Invoicing\Qr\EpcEncoder;
use App\Services\Invoicing\Qr\PayBySquareEncoder;
use App\Services\Invoicing\Qr\QrRenderer;
use Throwable;

/**
 * Z faktúry spraví QR platbu pripravenú na vloženie do PDF alebo HTML.
 */
class PaymentQrGenerator
{
    public function __construct(
        private readonly PayBySquareEncoder $payBySquare,
        private readonly EpcEncoder $epc,
        private readonly QrRenderer $renderer,
    ) {}

    /**
     * @return array{data_uri: string, format: string, payload: string}|null
     */
    public function forInvoice(Invoice $invoice): ?array
    {
        if (! config('invoicing.qr.enabled')) {
            return null;
        }

        // Dobropis sa neplatí a hotovosť nepotrebuje prevodný príkaz.
        if ($invoice->isCreditNote() || $invoice->outstandingCents() <= 0) {
            return null;
        }

        if (! ($invoice->payment_method ?? PaymentMethod::Transfer)->needsBankDetails()) {
            return null;
        }

        $supplier = $invoice->supplier_snapshot ?: config('invoicing.supplier');
        $iban = $supplier['iban'] ?? null;

        if (blank($iban)) {
            return null;
        }

        $payment = [
            'iban' => $iban,
            'swift' => $supplier['swift'] ?? null,
            'amount' => $invoice->outstandingCents() / 100,
            'currency' => $invoice->currency,
            'due_date' => $invoice->due_at?->toDateString(),
            'variable_symbol' => $invoice->variable_symbol,
            'constant_symbol' => $invoice->constant_symbol,
            'specific_symbol' => $invoice->specific_symbol,
            'invoice_id' => $invoice->number,
            'note' => 'Faktura '.$invoice->number,
            'beneficiary_name' => $supplier['name'] ?? null,
            'beneficiary_street' => $supplier['street'] ?? null,
            'beneficiary_city' => trim(($supplier['postal_code'] ?? '').' '.($supplier['city'] ?? '')),
        ];

        $format = config('invoicing.qr.format', 'pay_by_square');

        try {
            $payload = $format === 'epc'
                ? $this->epc->encode($payment)
                : $this->payBySquare->encode($payment);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        $dataUri = $this->renderer->dataUri($payload, (int) config('invoicing.qr.size', 320));

        if (! $dataUri) {
            return null;
        }

        return [
            'data_uri' => $dataUri,
            'format' => $format,
            'payload' => $payload,
        ];
    }
}
