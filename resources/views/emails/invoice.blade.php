@php
    use App\Enums\InvoiceType;

    /** @var \App\Models\Invoice $invoice */
    $qr = app(\App\Services\Invoicing\PaymentQrGenerator::class)->forInvoice($invoice);

    $label = match ($invoice->type) {
        InvoiceType::Proforma => 'Zálohová faktúra',
        InvoiceType::CreditNote => 'Dobropis',
        default => 'Faktúra',
    };

    $money = fn (int $cents) => number_format($cents / 100, 2, ',', ' ') . ' ' . $invoice->currency;
    $customerName = $invoice->billing_snapshot['name'] ?? $invoice->organization->name;
@endphp

@component('emails.layout', ['supplier' => $supplier, 'title' => $label . ' ' . $invoice->number, 'preview' => $label . ' č. ' . $invoice->number . ' na ' . $money($invoice->total_cents)])

    <p style="margin:0 0 6px; font-size:13px; color:#64748b;">Dobrý deň,</p>

    <h1 style="margin:0 0 6px; font-size:22px; line-height:1.25; font-weight:700; color:#0f172a; letter-spacing:-.4px;">
        {{ $label }} č. {{ $invoice->number }}
    </h1>

    <p style="margin:0 0 22px; font-size:14px; line-height:1.6; color:#475569;">
        @if ($invoice->isCreditNote())
            posielame vám dobropis k faktúre č. {{ $invoice->parent?->number }}.
            Suma {{ $money(abs($invoice->total_cents)) }} vám bude vrátená na účet.
        @elseif ($invoice->type === InvoiceType::Proforma)
            posielame vám zálohovú faktúru. Po jej úhrade vám obratom zašleme
            riadnu faktúru – daňový doklad.
        @else
            v prílohe posielame faktúru za služby pre firmu
            <strong style="color:#0f172a;">{{ $customerName }}</strong>.
        @endif
    </p>

    @if ($customMessage)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 22px;">
            <tr>
                <td style="background:#f8fafc; border-left:3px solid #cbd5e1; border-radius:0 8px 8px 0; padding:12px 16px; font-size:14px; line-height:1.6; color:#334155;">
                    {!! nl2br(e($customMessage)) !!}
                </td>
            </tr>
        </table>
    @endif

    {{-- Zhrnutie --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="margin:0 0 22px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px;">
        <tr>
            <td style="padding:18px 20px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:14px;">
                    <tr>
                        <td style="padding:4px 0; color:#64748b;">Suma na úhradu</td>
                        <td style="padding:4px 0; text-align:right; font-weight:700; font-size:19px; color:#4338ca; white-space:nowrap;">
                            {{ $money($invoice->outstandingCents() ?: $invoice->total_cents) }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:4px 0; color:#64748b;">Splatnosť</td>
                        <td style="padding:4px 0; text-align:right; font-weight:600; color:#0f172a;">
                            {{ $invoice->due_at?->format('j. n. Y') ?? '—' }}
                        </td>
                    </tr>
                    @if ($invoice->payment_method->needsBankDetails())
                        <tr>
                            <td style="padding:4px 0; color:#64748b;">Variabilný symbol</td>
                            <td style="padding:4px 0; text-align:right; font-weight:600; color:#0f172a;">
                                {{ $invoice->variable_symbol }}
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:4px 0; color:#64748b;">IBAN</td>
                            <td style="padding:4px 0; text-align:right; font-weight:600; color:#0f172a; white-space:nowrap; font-size:13px;">
                                {{ trim(chunk_split($supplier['iban'], 4, ' ')) }}
                            </td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    {{-- QR platba --}}
    @if ($qr)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 22px;">
            <tr>
                <td width="132" style="padding-right:16px;" valign="middle">
                    <img src="{{ $qr['data_uri'] }}" width="120" height="120" alt="QR platba"
                         style="display:block; border:1px solid #e2e8f0; border-radius:8px;">
                </td>
                <td valign="middle" style="font-size:13px; line-height:1.6; color:#475569;">
                    <strong style="color:#0f172a;">Zaplaťte za tri sekundy</strong><br>
                    Naskenujte {{ $qr['format'] === 'epc' ? 'EPC QR' : 'PAY by square' }} kód
                    v mobilnej aplikácii svojej banky. Suma, IBAN aj variabilný symbol
                    sa doplnia samy.
                </td>
            </tr>
        </table>
    @endif

    {{-- Položky --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 22px; font-size:13px;">
        @foreach ($invoice->items as $item)
            <tr>
                <td style="padding:8px 0; border-bottom:1px solid #f1f5f9; color:#334155;">
                    {{ $item->description }}
                    @if ($period = $item->periodLabel())
                        <br><span style="color:#94a3b8; font-size:12px;">{{ $period }}</span>
                    @endif
                </td>
                <td style="padding:8px 0; border-bottom:1px solid #f1f5f9; text-align:right; white-space:nowrap; color:#0f172a; font-weight:600;">
                    {{ number_format($item->subtotal_cents / 100, 2, ',', ' ') }}
                </td>
            </tr>
        @endforeach
    </table>

    <p style="margin:0 0 4px; font-size:13px; line-height:1.6; color:#64748b;">
        Faktúru v PDF nájdete v prílohe tohto e-mailu.
        @if ($invoice->vat_note)
            <br><span style="color:#92400e;">{{ $invoice->vat_note }}</span>
        @endif
    </p>

    <p style="margin:20px 0 0; font-size:14px; color:#475569;">
        Ďakujeme,<br>
        <strong style="color:#0f172a;">{{ $supplier['name'] }}</strong>
    </p>

@endcomponent
