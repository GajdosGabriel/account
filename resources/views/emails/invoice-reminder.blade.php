@php
    /** @var \App\Models\Invoice $invoice */
    $qr = app(\App\Services\Invoicing\PaymentQrGenerator::class)->forInvoice($invoice);
    $money = fn (int $cents) => number_format($cents / 100, 2, ',', ' ') . ' ' . $invoice->currency;

    $accent = match ($tone) {
        'final' => '#e11d48',
        'firm' => '#f59e0b',
        default => '#4f46e5',
    };

    $heading = match ($tone) {
        'final' => 'Posledná výzva na úhradu',
        'firm' => 'Faktúra je stále neuhradená',
        default => 'Pripomíname splatnosť faktúry',
    };
@endphp

@component('emails.layout', [
    'supplier' => $supplier,
    'accent' => $accent,
    'title' => $heading,
    'preview' => 'Faktúra č. ' . $invoice->number . ' je ' . $daysOverdue . ' dní po splatnosti.',
])

    <p style="margin:0 0 6px; font-size:13px; color:#64748b;">Dobrý deň,</p>

    <h1 style="margin:0 0 12px; font-size:22px; line-height:1.25; font-weight:700; color:#0f172a; letter-spacing:-.4px;">
        {{ $heading }}
    </h1>

    <p style="margin:0 0 22px; font-size:14px; line-height:1.65; color:#475569;">
        @if ($tone === 'final')
            faktúra č. <strong style="color:#0f172a;">{{ $invoice->number }}</strong> je
            <strong style="color:{{ $accent }};">{{ $daysOverdue }} dní po splatnosti</strong>.
            Ak úhradu neevidujeme do siedmich dní, budeme nútení dočasne pozastaviť
            prístup k službe. Radi by sme sa tomu vyhli – ak niečo bráni úhrade,
            ozvite sa nám a nájdeme riešenie.
        @elseif ($tone === 'firm')
            faktúra č. <strong style="color:#0f172a;">{{ $invoice->number }}</strong> je
            <strong style="color:{{ $accent }};">{{ $daysOverdue }} dní po splatnosti</strong>
            a úhradu sme zatiaľ nezaznamenali. Prosíme vás o jej vyrovnanie.
        @else
            možno vám v zhone unikla – faktúra č.
            <strong style="color:#0f172a;">{{ $invoice->number }}</strong> mala splatnosť
            {{ $invoice->due_at?->format('j. n. Y') }}. Ak ste ju už uhradili,
            tento e-mail prosím ignorujte.
        @endif
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="margin:0 0 22px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px;">
        <tr>
            <td style="padding:18px 20px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:14px;">
                    <tr>
                        <td style="padding:4px 0; color:#64748b;">Zostáva uhradiť</td>
                        <td style="padding:4px 0; text-align:right; font-weight:700; font-size:19px; color:{{ $accent }}; white-space:nowrap;">
                            {{ $money($invoice->outstandingCents()) }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:4px 0; color:#64748b;">Splatnosť bola</td>
                        <td style="padding:4px 0; text-align:right; font-weight:600; color:#0f172a;">
                            {{ $invoice->due_at?->format('j. n. Y') }}
                        </td>
                    </tr>
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
                </table>
            </td>
        </tr>
    </table>

    @if ($qr)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 22px;">
            <tr>
                <td width="132" style="padding-right:16px;" valign="middle">
                    <img src="{{ $qr['data_uri'] }}" width="120" height="120" alt="QR platba"
                         style="display:block; border:1px solid #e2e8f0; border-radius:8px;">
                </td>
                <td valign="middle" style="font-size:13px; line-height:1.6; color:#475569;">
                    <strong style="color:#0f172a;">Najrýchlejšia cesta</strong><br>
                    Naskenujte QR kód v aplikácii banky – príkaz sa vyplní sám.
                </td>
            </tr>
        </table>
    @endif

    <p style="margin:0; font-size:13px; line-height:1.6; color:#64748b;">
        Kópiu faktúry prikladáme v PDF. Ak je niečo nejasné, stačí odpovedať na tento e-mail.
    </p>

    <p style="margin:20px 0 0; font-size:14px; color:#475569;">
        S pozdravom,<br>
        <strong style="color:#0f172a;">{{ $supplier['name'] }}</strong>
    </p>

@endcomponent
