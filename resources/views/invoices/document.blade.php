@php
    use App\Enums\InvoiceType;

    /** @var \App\Models\Invoice $invoice */
    $supplier = $invoice->supplier_snapshot ?: config('invoicing.supplier');
    $customer = $invoice->billing_snapshot ?: $invoice->organization->billingSnapshot();

    $money = fn (?int $cents) => number_format(($cents ?? 0) / 100, 2, ',', ' ') . ' ' . $invoice->currency;
    $date = fn ($value) => $value ? $value->format('j. n. Y') : '—';

    $title = match ($invoice->type) {
        InvoiceType::Proforma => 'Zálohová faktúra',
        InvoiceType::CreditNote => 'Dobropis',
        default => 'Faktúra',
    };

    $subtitle = match ($invoice->type) {
        InvoiceType::Proforma => 'Nie je daňový doklad',
        InvoiceType::CreditNote => 'Opravný daňový doklad',
        default => 'Daňový doklad',
    };

    $customerAddress = $customer['address'] ?? [];
    $showVat = collect($invoice->vat_summary ?? [])->contains(fn ($row) => (float) $row['rate'] > 0);
    $paid = $invoice->paid_cents > 0;
@endphp
<!DOCTYPE html>
<html lang="{{ $invoice->locale ?? 'sk' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} {{ $invoice->number }}</title>
    <style>
        @page { margin: 18mm 15mm 20mm 15mm; }

        * { box-sizing: border-box; }

        body {
            font-family: {{ ($pdf ?? false) ? "'DejaVu Sans', sans-serif" : "-apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif" }};
            font-size: 9.5pt;
            line-height: 1.45;
            color: #1e293b;
            margin: 0;
            padding: 0;
        }

        @if (! ($pdf ?? false))
            body { background: #f1f5f9; padding: 24px 0; }
            .sheet {
                width: 210mm;
                min-height: 297mm;
                margin: 0 auto;
                padding: 18mm 15mm 20mm;
                background: #fff;
                box-shadow: 0 10px 40px rgba(15, 23, 42, .12);
                border-radius: 4px;
            }
            @media print {
                body { background: #fff; padding: 0; }
                .sheet { width: auto; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
            }
        @else
            .sheet { padding: 0; }
        @endif

        table { border-collapse: collapse; width: 100%; }
        td, th { vertical-align: top; }

        .muted { color: #64748b; }
        .tiny { font-size: 8pt; }
        .right { text-align: right; }
        .center { text-align: center; }
        .strong { font-weight: 700; }
        .nowrap { white-space: nowrap; }

        /* ---------- hlavička ---------- */
        .head { width: 100%; margin-bottom: 22px; }
        .head .brand { font-size: 15pt; font-weight: 700; color: #0f172a; letter-spacing: -.3px; }
        .head .logo { max-height: 52px; max-width: 190px; }

        .doc-title {
            font-size: 20pt;
            font-weight: 700;
            letter-spacing: -.5px;
            color: #4338ca;
            line-height: 1.1;
        }
        .doc-number { font-size: 13pt; font-weight: 700; color: #0f172a; }
        .doc-kind {
            display: inline-block;
            margin-top: 4px;
            padding: 2px 8px;
            font-size: 7.5pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .6px;
            color: #4338ca;
            background: #eef2ff;
            border-radius: 3px;
        }

        /* ---------- strany ---------- */
        .parties { margin-bottom: 16px; }
        .parties td { width: 50%; padding: 0; }
        .parties td:first-child { padding-right: 8px; }
        .parties td:last-child { padding-left: 8px; }

        .party {
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            padding: 11px 13px;
            height: 100%;
        }
        .party.customer { border-color: #c7d2fe; background: #f8faff; }
        .party .label {
            font-size: 7.5pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .8px;
            color: #6366f1;
            margin-bottom: 5px;
        }
        .party .name { font-size: 11pt; font-weight: 700; color: #0f172a; margin-bottom: 2px; }
        .party .addr { color: #334155; }
        .party .ids { margin-top: 7px; padding-top: 7px; border-top: 1px solid #e2e8f0; }
        .party .ids td { padding: .5px 0; font-size: 8.5pt; }
        .party .ids td:first-child { color: #64748b; width: 62px; }

        /* ---------- platobné údaje ---------- */
        .meta { margin-bottom: 18px; }
        .meta td { width: 50%; padding: 0; }
        .meta td:first-child { padding-right: 8px; }
        .meta td:last-child { padding-left: 8px; }
        .meta .box {
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            padding: 9px 13px;
        }
        .meta .box table td { font-size: 8.7pt; padding: 1.5px 0; width: auto; }
        .meta .box table td:first-child { color: #64748b; width: 47%; padding-right: 6px; }
        .meta .box table td:last-child { font-weight: 600; color: #0f172a; text-align: right; }
        .meta .box .heading {
            font-size: 7.5pt; font-weight: 700; text-transform: uppercase;
            letter-spacing: .8px; color: #6366f1; margin-bottom: 5px;
        }
        .vs-highlight { font-size: 10pt !important; color: #4338ca !important; }

        /* ---------- položky ---------- */
        .items { margin-bottom: 14px; }
        .items thead th {
            font-size: 7.5pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .6px;
            color: #475569;
            background: #f1f5f9;
            padding: 7px 8px;
            text-align: right;
            border-bottom: 1.5px solid #cbd5e1;
        }
        .items thead th.l { text-align: left; }
        .items tbody td {
            padding: 8px;
            border-bottom: 1px solid #f1f5f9;
            text-align: right;
        }
        .items tbody td.l { text-align: left; }
        .items tbody tr:last-child td { border-bottom: 1px solid #cbd5e1; }
        .items .desc { font-weight: 600; color: #0f172a; }
        .items .detail { font-size: 8pt; color: #64748b; margin-top: 1px; }

        /* ---------- súčty ---------- */
        .summary { margin-bottom: 16px; }
        .summary > td { padding: 0; vertical-align: top; }
        .summary .left-col { width: 52%; padding-right: 10px; }
        .summary .right-col { width: 48%; padding-left: 10px; }

        .vat-table th, .vat-table td {
            font-size: 8.3pt; padding: 4px 7px; text-align: right;
            border-bottom: 1px solid #f1f5f9;
        }
        .vat-table th {
            font-weight: 700; color: #475569; background: #f8fafc;
            text-transform: uppercase; font-size: 7.2pt; letter-spacing: .5px;
        }
        .vat-table th.l, .vat-table td.l { text-align: left; }

        .totals td { padding: 4px 0; font-size: 9.5pt; }
        .totals td:first-child { color: #475569; }
        .totals td:last-child { text-align: right; font-weight: 600; color: #0f172a; }
        .totals .grand td {
            padding-top: 9px;
            border-top: 2px solid #4338ca;
            font-size: 13pt;
            font-weight: 700;
            color: #4338ca;
        }
        .totals .sub td { font-size: 8.7pt; color: #64748b; padding-top: 2px; }

        /* ---------- QR ---------- */
        .pay { margin-bottom: 16px; }
        .pay td { padding: 0; vertical-align: middle; }
        .pay .qr-cell { width: 118px; padding-right: 14px; }
        .pay .qr-cell img { width: 108px; height: 108px; display: block; }
        .pay-box {
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            background: #f8fafc;
            padding: 11px 14px;
        }

        /* ---------- pätka ---------- */
        .note {
            border-left: 3px solid #c7d2fe;
            background: #f8faff;
            padding: 8px 12px;
            margin-bottom: 12px;
            font-size: 8.7pt;
            color: #334155;
        }
        .vat-note {
            border-left-color: #fbbf24;
            background: #fffbeb;
            color: #78350f;
            font-weight: 600;
        }
        .stamp {
            margin-top: 26px;
            padding-top: 6px;
            border-top: 1px solid #e2e8f0;
        }
        .stamp td { font-size: 8.3pt; color: #64748b; padding-top: 4px; }
        .sign-line {
            margin-top: 30px;
            border-top: 1px dotted #94a3b8;
            width: 170px;
            padding-top: 3px;
            font-size: 7.5pt;
            color: #94a3b8;
        }
        .footer {
            margin-top: 14px;
            padding-top: 8px;
            border-top: 1px solid #e2e8f0;
            font-size: 7.5pt;
            color: #94a3b8;
            text-align: center;
            line-height: 1.5;
        }
        .paid-stamp {
            float: right;
            border: 2.5px solid #10b981;
            color: #059669;
            font-weight: 700;
            font-size: 12pt;
            letter-spacing: 1.5px;
            padding: 5px 16px;
            border-radius: 4px;
            text-transform: uppercase;
        }
        .cancelled-stamp { border-color: #ef4444; color: #dc2626; }
    </style>
</head>
<body>
<div class="sheet">

    {{-- ============ HLAVIČKA ============ --}}
    <table class="head">
        <tr>
            <td style="width: 55%;">
                @if (! empty($supplier['logo']) && ($logo = $logoData ?? null))
                    <img src="{{ $logo }}" alt="" class="logo">
                @else
                    <div class="brand">{{ $supplier['name'] }}</div>
                @endif
                <div class="muted tiny" style="margin-top: 4px;">
                    {{ $supplier['street'] }}, {{ $supplier['postal_code'] }} {{ $supplier['city'] }}
                </div>
            </td>
            <td class="right" style="width: 45%;">
                <div class="doc-title">{{ $title }}</div>
                <div class="doc-number">č. {{ $invoice->number ?? '(koncept)' }}</div>
                <div class="doc-kind">{{ $subtitle }}</div>
            </td>
        </tr>
    </table>

    {{-- ============ DODÁVATEĽ / ODBERATEĽ ============ --}}
    <table class="parties">
        <tr>
            <td>
                <div class="party">
                    <div class="label">Dodávateľ</div>
                    <div class="name">{{ $supplier['name'] }}</div>
                    <div class="addr">
                        {{ $supplier['street'] }}<br>
                        {{ $supplier['postal_code'] }} {{ $supplier['city'] }}<br>
                        {{ $supplier['country'] === 'SK' ? 'Slovenská republika' : $supplier['country'] }}
                    </div>
                    <table class="ids">
                        <tr><td>IČO</td><td class="strong">{{ $supplier['ico'] ?: '—' }}</td></tr>
                        <tr><td>DIČ</td><td class="strong">{{ $supplier['dic'] ?: '—' }}</td></tr>
                        <tr>
                            <td>IČ DPH</td>
                            <td class="strong">
                                {{ $supplier['ic_dph'] ?: 'neplatiteľ DPH' }}
                            </td>
                        </tr>
                    </table>
                </div>
            </td>
            <td>
                <div class="party customer">
                    <div class="label">Odberateľ</div>
                    <div class="name">{{ $customer['name'] }}</div>
                    <div class="addr">
                        {{ $customerAddress['street'] ?? '' }}<br>
                        {{ $customerAddress['postal_code'] ?? '' }} {{ $customerAddress['city'] ?? '' }}<br>
                        {{ ($customerAddress['country'] ?? 'SK') === 'SK' ? 'Slovenská republika' : ($customerAddress['country'] ?? '') }}
                    </div>
                    <table class="ids">
                        <tr><td>IČO</td><td class="strong">{{ $customer['ico'] ?: '—' }}</td></tr>
                        <tr><td>DIČ</td><td class="strong">{{ $customer['dic'] ?: '—' }}</td></tr>
                        <tr>
                            <td>IČ DPH</td>
                            <td class="strong">{{ $customer['ic_dph'] ?: 'neplatiteľ DPH' }}</td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    {{-- ============ PLATBA A DÁTUMY ============ --}}
    <table class="meta">
        <tr>
            <td>
                <div class="box">
                    <div class="heading">Platobné údaje</div>
                    <table>
                        <tr><td>Spôsob úhrady</td><td>{{ $invoice->payment_method->label() }}</td></tr>
                        @if ($invoice->payment_method->needsBankDetails())
                            <tr><td>IBAN</td><td class="nowrap">{{ trim(chunk_split($supplier['iban'], 4, ' ')) }}</td></tr>
                            <tr><td>SWIFT / BIC</td><td>{{ $supplier['swift'] }}</td></tr>
                            <tr><td>Banka</td><td>{{ $supplier['bank_name'] }}</td></tr>
                        @endif
                        <tr><td>Variabilný symbol</td><td class="vs-highlight">{{ $invoice->variable_symbol ?: '—' }}</td></tr>
                        @if ($invoice->constant_symbol)
                            <tr><td>Konštantný symbol</td><td>{{ $invoice->constant_symbol }}</td></tr>
                        @endif
                        @if ($invoice->specific_symbol)
                            <tr><td>Špecifický symbol</td><td>{{ $invoice->specific_symbol }}</td></tr>
                        @endif
                    </table>
                </div>
            </td>
            <td>
                <div class="box">
                    <div class="heading">Dátumy</div>
                    <table>
                        <tr><td>Dátum vystavenia</td><td>{{ $date($invoice->issued_at) }}</td></tr>
                        <tr>
                            <td>Dátum dodania</td>
                            <td>{{ $date($invoice->delivered_at) }}</td>
                        </tr>
                        <tr>
                            <td>Dátum splatnosti</td>
                            <td style="color: {{ $invoice->isOverdue() ? '#dc2626' : '#0f172a' }};">
                                {{ $date($invoice->due_at) }}
                            </td>
                        </tr>
                        @if ($invoice->paid_at)
                            <tr><td>Uhradené dňa</td><td>{{ $invoice->paid_at->format('j. n. Y') }}</td></tr>
                        @endif
                        {{-- Doklad je celý po slovensky, preto aj typ – nie v jazyku rozhrania. --}}
                        <tr><td>Forma dokladu</td><td>{{ __('enums.invoice_type_short.'.$invoice->type->value, [], 'sk') }}</td></tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    {{-- ============ POLOŽKY ============ --}}
    <table class="items">
        <thead>
            <tr>
                <th class="l" style="width: 42%;">Popis</th>
                <th style="width: 10%;">Množstvo</th>
                <th style="width: 9%;">MJ</th>
                <th style="width: 13%;">Cena / MJ</th>
                @if ($showVat)
                    <th style="width: 8%;">DPH</th>
                @endif
                <th style="width: 18%;">Spolu bez DPH</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($invoice->items as $item)
                <tr>
                    <td class="l">
                        <div class="desc">{{ $item->description }}</div>
                        @if ($item->detail)
                            <div class="detail">{{ $item->detail }}</div>
                        @endif
                        @if ($period = $item->periodLabel())
                            <div class="detail">Obdobie: {{ $period }}</div>
                        @endif
                        @if ((float) $item->discount_percent > 0)
                            <div class="detail">Zľava {{ rtrim(rtrim(number_format((float) $item->discount_percent, 2, ',', ''), '0'), ',') }} %</div>
                        @endif
                    </td>
                    <td>{{ rtrim(rtrim(number_format((float) $item->quantity, 3, ',', ' '), '0'), ',') }}</td>
                    <td>{{ $item->unit }}</td>
                    <td class="nowrap">{{ number_format($item->unitPrice(), 2, ',', ' ') }}</td>
                    @if ($showVat)
                        <td>{{ rtrim(rtrim(number_format((float) $item->vat_rate, 2, ',', ''), '0'), ',') }} %</td>
                    @endif
                    <td class="nowrap strong">{{ number_format($item->subtotal_cents / 100, 2, ',', ' ') }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="l muted">Doklad zatiaľ nemá žiadne položky.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- ============ REKAPITULÁCIA A SÚČTY ============ --}}
    <table class="summary">
        <tr>
            <td class="left-col">
                @if ($showVat)
                    <table class="vat-table">
                        <tr>
                            <th class="l">Rekapitulácia DPH</th>
                            <th>Základ</th>
                            <th>DPH</th>
                            <th>Spolu</th>
                        </tr>
                        @foreach ($invoice->vat_summary ?? [] as $row)
                            <tr>
                                <td class="l">{{ rtrim(rtrim(number_format((float) $row['rate'], 2, ',', ''), '0'), ',') }} %</td>
                                <td class="nowrap">{{ number_format($row['base_cents'] / 100, 2, ',', ' ') }}</td>
                                <td class="nowrap">{{ number_format($row['vat_cents'] / 100, 2, ',', ' ') }}</td>
                                <td class="nowrap">{{ number_format(($row['base_cents'] + $row['vat_cents']) / 100, 2, ',', ' ') }}</td>
                            </tr>
                        @endforeach
                    </table>
                @endif
            </td>
            <td class="right-col">
                <table class="totals">
                    <tr>
                        <td>Základ dane</td>
                        <td class="nowrap">{{ $money($invoice->subtotal_cents) }}</td>
                    </tr>
                    @if ($showVat)
                        <tr>
                            <td>DPH</td>
                            <td class="nowrap">{{ $money($invoice->vat_cents) }}</td>
                        </tr>
                    @endif
                    @if ($invoice->rounding_cents !== 0)
                        <tr>
                            <td>Zaokrúhlenie</td>
                            <td class="nowrap">{{ $money($invoice->rounding_cents) }}</td>
                        </tr>
                    @endif
                    <tr class="grand">
                        <td>{{ $invoice->isCreditNote() ? 'K vráteniu' : 'Celkom na úhradu' }}</td>
                        <td class="nowrap">{{ $money($invoice->total_cents) }}</td>
                    </tr>
                    @if ($paid && ! $invoice->isPaid())
                        <tr class="sub">
                            <td>Už uhradené</td>
                            <td class="nowrap">−{{ $money($invoice->paid_cents) }}</td>
                        </tr>
                        <tr class="sub">
                            <td class="strong" style="color:#4338ca;">Zostáva uhradiť</td>
                            <td class="nowrap strong" style="color:#4338ca;">{{ $money($invoice->outstandingCents()) }}</td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    {{-- ============ QR PLATBA ============ --}}
    @if ($qr ?? null)
        <table class="pay">
            <tr>
                <td class="qr-cell">
                    <img src="{{ $qr['data_uri'] }}" alt="QR platba">
                </td>
                <td>
                    <div class="pay-box">
                        <div class="strong" style="color:#0f172a;">
                            {{ $qr['format'] === 'epc' ? 'EPC QR – SEPA prevod' : 'PAY by square' }}
                        </div>
                        <div class="muted tiny" style="margin-top: 3px;">
                            Naskenujte kód v mobilnej aplikácii svojej banky. Suma, IBAN aj variabilný
                            symbol sa vyplnia automaticky – nič neprepisujete ručne.
                        </div>
                        <div class="tiny" style="margin-top: 6px;">
                            <span class="muted">Suma</span>
                            <span class="strong">{{ $money($invoice->outstandingCents()) }}</span>
                            &nbsp;·&nbsp;
                            <span class="muted">VS</span>
                            <span class="strong">{{ $invoice->variable_symbol }}</span>
                        </div>
                    </div>
                </td>
            </tr>
        </table>
    @endif

    {{-- ============ POZNÁMKY ============ --}}
    @if ($invoice->vat_note)
        <div class="note vat-note">{{ $invoice->vat_note }}</div>
    @endif

    @if ($invoice->type === \App\Enums\InvoiceType::Proforma)
        <div class="note">
            Tento doklad nie je daňovým dokladom. Po prijatí úhrady vám zašleme
            riadnu faktúru – daňový doklad.
        </div>
    @endif

    @if ($invoice->parent && $invoice->isCreditNote())
        <div class="note">
            Dobropis k faktúre č. <strong>{{ $invoice->parent->number }}</strong>
            zo dňa {{ $date($invoice->parent->issued_at) }}.
        </div>
    @endif

    @if ($invoice->note)
        <div class="note">{!! nl2br(e($invoice->note)) !!}</div>
    @endif

    {{-- ============ PÄTKA ============ --}}
    <table class="stamp">
        <tr>
            <td style="width: 60%;">
                @if ($supplier['registration'] ?? null)
                    <div>{{ $supplier['registration'] }}</div>
                @endif
                <div>
                    @if ($supplier['email'] ?? null){{ $supplier['email'] }}@endif
                    @if ($supplier['phone'] ?? null) · {{ $supplier['phone'] }}@endif
                    @if ($supplier['web'] ?? null) · {{ $supplier['web'] }}@endif
                </div>
                @if ($supplier['issued_by'] ?? null)
                    <div style="margin-top: 4px;">Vystavil: {{ $supplier['issued_by'] }}</div>
                @endif
            </td>
            <td class="right" style="width: 40%;">
                @if ($invoice->isPaid())
                    <div class="paid-stamp">Uhradené</div>
                @elseif ($invoice->isCancelled())
                    <div class="paid-stamp cancelled-stamp">Storno</div>
                @else
                    <div class="sign-line" style="float: right;">Pečiatka a podpis</div>
                @endif
            </td>
        </tr>
    </table>

    <div class="footer">
        Doklad bol vystavený v elektronickej podobe a je platný bez pečiatky a podpisu.<br>
        {{ $supplier['name'] }} · IČO {{ $supplier['ico'] }}
        @if ($supplier['ic_dph'] ?? null) · IČ DPH {{ $supplier['ic_dph'] }} @endif
    </div>

</div>
</body>
</html>
