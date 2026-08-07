<?php

namespace App\Services\Invoicing;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Organization;
use App\Models\Subscription;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Všetko, čo mení stav dokladu, ide cez túto triedu.
 *
 * Controller nikdy nesiaha na Invoice::create() priamo – inak by sa
 * pravidlá (kedy sa smie prideliť číslo, kedy sa zamyká obsah,
 * čo sa zapíše do histórie) rozliezli po celej aplikácii.
 */
class InvoiceService
{
    public function __construct(
        private readonly InvoiceNumberGenerator $numbers,
        private readonly VatResolver $vat,
    ) {}

    /* ===============================================================
     | Vytvorenie konceptu
     |===============================================================*/

    /**
     * Prázdny koncept pre firmu – položky sa dopĺňajú v UI.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function draft(Organization $organization, InvoiceType $type = InvoiceType::Invoice, array $attributes = []): Invoice
    {
        $vat = $this->vat->resolve($organization);
        $dueDays = $organization->payment_terms_days ?: (int) config('invoicing.defaults.due_days');

        $invoice = new Invoice([
            'organization_id' => $organization->id,
            'type' => $type,
            'status' => InvoiceStatus::Draft,
            'currency' => $organization->currency ?: config('invoicing.defaults.currency'),
            'locale' => $organization->invoice_language ?: config('invoicing.defaults.locale'),
            'payment_method' => config('invoicing.defaults.payment_method'),
            'constant_symbol' => config('invoicing.defaults.constant_symbol'),
            'reverse_charge' => $vat['reverse_charge'],
            'vat_rate' => $vat['rate'],
            'vat_note' => $vat['note'],
            // Explicitne, nie cez DB default – recalculate() s nimi počíta
            // ešte predtým, než sa model prvýkrát načíta z databázy.
            'subtotal_cents' => 0,
            'discount_cents' => 0,
            'vat_cents' => 0,
            'total_cents' => 0,
            'rounding_cents' => 0,
            'paid_cents' => 0,
            'note' => config('invoicing.defaults.footer_note'),
            'issued_at' => Carbon::today(),
            'delivered_at' => Carbon::today(),
            'due_at' => Carbon::today()->addDays($dueDays),
            'created_by' => auth()->id(),
        ]);

        $invoice->fill($attributes);
        $invoice->save();

        $invoice->recordEvent('created', 'Vytvorený koncept dokladu.');

        return $invoice;
    }

    /**
     * Koncept faktúry za jedno obdobie predplatného.
     * Toto je to, čo volá automatická fakturácia.
     */
    public function draftForSubscription(Subscription $subscription, ?Carbon $periodStart = null, ?Carbon $periodEnd = null): Invoice
    {
        $subscription->loadMissing(['organization', 'plan.product']);

        $organization = $subscription->organization;
        $plan = $subscription->plan;

        if (! $plan) {
            throw new DomainException('Predplatné nemá priradený plán, faktúru sa nedá vystaviť.');
        }

        $periodStart ??= $subscription->current_period_start?->copy() ?? Carbon::today();
        $periodEnd ??= $subscription->current_period_end?->copy() ?? $periodStart->copy()->addMonth()->subDay();

        $invoice = $this->draft($organization, InvoiceType::Invoice, [
            'subscription_id' => $subscription->id,
        ]);

        $interval = $plan->interval === 'year' ? 'ročné' : 'mesačné';

        $this->addItem($invoice, [
            'product_id' => $plan->product_id,
            'plan_id' => $plan->id,
            'description' => $plan->product->name.' – '.$plan->name,
            'detail' => 'Predplatné ('.$interval.')',
            'quantity' => max(1, (int) $subscription->quantity),
            'unit' => $plan->interval === 'year' ? 'rok' : 'mesiac',
            'unit_price' => $plan->price_cents * 100,   // centy -> stotiny centa
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
        ]);

        return $invoice->refresh();
    }

    /* ===============================================================
     | Položky
     |===============================================================*/

    /** @param array<string, mixed> $data */
    public function addItem(Invoice $invoice, array $data): InvoiceItem
    {
        $this->assertEditable($invoice);

        $item = $invoice->items()->create([
            'product_id' => $data['product_id'] ?? null,
            'plan_id' => $data['plan_id'] ?? null,
            'description' => $data['description'],
            'detail' => $data['detail'] ?? null,
            'quantity' => $data['quantity'] ?? 1,
            'unit' => $data['unit'] ?? config('invoicing.defaults.unit'),
            'unit_price' => (int) ($data['unit_price'] ?? 0),
            'discount_percent' => $data['discount_percent'] ?? 0,
            // Sadzbu určuje faktúra ako celok; pri reverse charge je vždy 0.
            'vat_rate' => $data['vat_rate'] ?? $invoice->vat_rate,
            'period_start' => $data['period_start'] ?? null,
            'period_end' => $data['period_end'] ?? null,
            'sort_order' => $data['sort_order'] ?? ($invoice->items()->max('sort_order') + 1),
        ]);

        $this->refreshTotals($invoice);

        return $item;
    }

    /** @param array<string, mixed> $data */
    public function updateItem(InvoiceItem $item, array $data): InvoiceItem
    {
        $this->assertEditable($item->invoice);

        $item->fill($data)->save();
        $this->refreshTotals($item->invoice);

        return $item;
    }

    public function removeItem(InvoiceItem $item): void
    {
        $invoice = $item->invoice;

        $this->assertEditable($invoice);

        $item->delete();
        $this->refreshTotals($invoice);
    }

    public function refreshTotals(Invoice $invoice): Invoice
    {
        $invoice->load('items');
        $invoice->recalculate()->save();

        return $invoice;
    }

    /* ===============================================================
     | Vystavenie
     |===============================================================*/

    /**
     * Pridelí číslo, odfotí fakturačné údaje a zamkne obsah.
     * Od tohto momentu sa doklad už nesmie meniť.
     */
    public function issue(Invoice $invoice, ?Carbon $issuedAt = null): Invoice
    {
        if (! $invoice->isDraft()) {
            throw new DomainException('Doklad je už vystavený.');
        }

        $invoice->loadMissing(['organization', 'items']);

        if ($invoice->items->isEmpty()) {
            throw new DomainException('Doklad nemá žiadne položky.');
        }

        if ($missing = $invoice->organization->missingBillingFields()) {
            throw new DomainException('Firme chýbajú fakturačné údaje: '.implode(', ', $missing).'.');
        }

        return DB::transaction(function () use ($invoice, $issuedAt) {
            $issuedAt ??= $invoice->issued_at ? Carbon::parse($invoice->issued_at) : Carbon::today();

            ['series' => $series, 'sequence' => $sequence, 'number' => $number]
                = $this->numbers->next($invoice->type, $issuedAt);

            $dueDays = $invoice->organization->payment_terms_days
                ?: (int) config('invoicing.defaults.due_days');

            $invoice->recalculate()->forceFill([
                'number' => $number,
                'number_series_id' => $series->id,
                'sequence' => $sequence,
                'status' => InvoiceStatus::Issued,
                'issued_at' => $issuedAt,
                'delivered_at' => $invoice->delivered_at ?? $issuedAt,
                'due_at' => $invoice->due_at ?? $issuedAt->copy()->addDays($dueDays),
                'variable_symbol' => $invoice->variable_symbol ?: $this->numbers->variableSymbol($number),
                'billing_snapshot' => $invoice->organization->billingSnapshot(),
                'supplier_snapshot' => $this->supplierSnapshot(),
            ])->save();

            $invoice->recordEvent('issued', "Doklad {$number} vystavený.", [
                'total_cents' => $invoice->total_cents,
            ]);

            return $invoice->refresh();
        });
    }

    /* ===============================================================
     | Úhrady
     |===============================================================*/

    /**
     * Zápis platby. Bez sumy sa doklad uhradí celý.
     * Čiastočné úhrady sa sčítavajú a stav sa dopočíta.
     */
    public function recordPayment(Invoice $invoice, ?int $amountCents = null, ?Carbon $paidAt = null, ?string $note = null): Invoice
    {
        if ($invoice->isDraft()) {
            throw new DomainException('Koncept sa nedá označiť ako uhradený – najprv ho vystav.');
        }

        if ($invoice->isCancelled()) {
            throw new DomainException('Stornovaný doklad sa nedá uhradiť.');
        }

        $amountCents ??= $invoice->outstandingCents();
        $paidAt ??= Carbon::now();

        $paid = $invoice->paid_cents + $amountCents;
        $fullyPaid = $paid >= $invoice->total_cents;

        $invoice->forceFill([
            'paid_cents' => $paid,
            'status' => $fullyPaid ? InvoiceStatus::Paid : InvoiceStatus::PartiallyPaid,
            'paid_at' => $fullyPaid ? $paidAt : $invoice->paid_at,
        ])->save();

        $invoice->recordEvent(
            $fullyPaid ? 'paid' : 'partially_paid',
            $note ?? ($fullyPaid
                ? 'Doklad označený ako uhradený.'
                : 'Prijatá čiastočná úhrada '.$invoice->formatMoney($amountCents).'.'),
            ['amount_cents' => $amountCents, 'paid_at' => $paidAt->toDateTimeString()],
        );

        return $invoice->refresh();
    }

    /* ===============================================================
     | Storno a dobropis
     |===============================================================*/

    /**
     * Storno.
     *
     * Vystavený daňový doklad sa v SR nesmie „zmiznúť“ – ak už išiel
     * zákazníkovi, opravuje sa dobropisom. Storno je tu len pre doklady,
     * ktoré ešte nikam neodišli, a pre zálohové faktúry.
     */
    public function cancel(Invoice $invoice, ?string $reason = null): Invoice
    {
        if ($invoice->isDraft()) {
            throw new DomainException('Koncept netreba stornovať – jednoducho ho zmaž.');
        }

        if ($invoice->isCancelled()) {
            return $invoice;
        }

        if ($invoice->paid_cents > 0) {
            throw new DomainException('Doklad má evidovanú úhradu. Vystav dobropis.');
        }

        $invoice->forceFill([
            'status' => InvoiceStatus::Cancelled,
            'cancelled_at' => now(),
        ])->save();

        $invoice->recordEvent('cancelled', $reason ?? 'Doklad stornovaný.');

        return $invoice->refresh();
    }

    /**
     * Dobropis k faktúre – kópia položiek so zápornými množstvami.
     * Vzniká rovno vystavený, pretože oprava má vlastné číslo v rade.
     *
     * @param  array<int, int>|null  $itemIds  Čiastočný dobropis len na vybrané položky.
     */
    public function creditNote(Invoice $invoice, ?array $itemIds = null, ?string $reason = null): Invoice
    {
        if ($invoice->type !== InvoiceType::Invoice) {
            throw new DomainException('Dobropis sa vystavuje len k riadnej faktúre.');
        }

        if ($invoice->isDraft()) {
            throw new DomainException('K nevystavenej faktúre nemá dobropis zmysel.');
        }

        $invoice->loadMissing(['organization', 'items']);

        return DB::transaction(function () use ($invoice, $itemIds, $reason) {
            $credit = $this->draft($invoice->organization, InvoiceType::CreditNote, [
                'subscription_id' => $invoice->subscription_id,
                'parent_invoice_id' => $invoice->id,
                'currency' => $invoice->currency,
                'locale' => $invoice->locale,
                'reverse_charge' => $invoice->reverse_charge,
                'vat_rate' => $invoice->vat_rate,
                'vat_note' => $invoice->vat_note,
                'payment_method' => $invoice->payment_method,
                'due_at' => Carbon::today(),
                'note' => $reason ?? "Dobropis k faktúre č. {$invoice->number}.",
            ]);

            $items = $itemIds
                ? $invoice->items->whereIn('id', $itemIds)
                : $invoice->items;

            if ($items->isEmpty()) {
                throw new DomainException('Nie sú vybrané žiadne položky na dobropisovanie.');
            }

            foreach ($items as $item) {
                $this->addItem($credit, [
                    'product_id' => $item->product_id,
                    'plan_id' => $item->plan_id,
                    'description' => $item->description,
                    'detail' => $item->detail,
                    'quantity' => -1 * (float) $item->quantity,
                    'unit' => $item->unit,
                    'unit_price' => $item->unit_price,
                    'discount_percent' => $item->discount_percent,
                    'vat_rate' => $item->vat_rate,
                    'period_start' => $item->period_start?->toDateString(),
                    'period_end' => $item->period_end?->toDateString(),
                    'sort_order' => $item->sort_order,
                ]);
            }

            $credit = $this->issue($credit->refresh());

            $invoice->recordEvent('credited', "Vystavený dobropis č. {$credit->number}.", [
                'credit_note_id' => $credit->id,
            ]);

            return $credit;
        });
    }

    /**
     * Riadna faktúra k uhradenej zálohovej faktúre.
     */
    public function invoiceFromProforma(Invoice $proforma): Invoice
    {
        if ($proforma->type !== InvoiceType::Proforma) {
            throw new DomainException('Zdrojový doklad nie je zálohová faktúra.');
        }

        $proforma->loadMissing(['organization', 'items']);

        return DB::transaction(function () use ($proforma) {
            $invoice = $this->draft($proforma->organization, InvoiceType::Invoice, [
                'subscription_id' => $proforma->subscription_id,
                'parent_invoice_id' => $proforma->id,
                'currency' => $proforma->currency,
                'locale' => $proforma->locale,
                'reverse_charge' => $proforma->reverse_charge,
                'vat_rate' => $proforma->vat_rate,
                'vat_note' => $proforma->vat_note,
                'payment_method' => $proforma->payment_method,
                'note' => "Vyúčtovanie zálohovej faktúry č. {$proforma->number}.",
            ]);

            foreach ($proforma->items as $item) {
                $this->addItem($invoice, [
                    'product_id' => $item->product_id,
                    'plan_id' => $item->plan_id,
                    'description' => $item->description,
                    'detail' => $item->detail,
                    'quantity' => $item->quantity,
                    'unit' => $item->unit,
                    'unit_price' => $item->unit_price,
                    'discount_percent' => $item->discount_percent,
                    'vat_rate' => $item->vat_rate,
                    'period_start' => $item->period_start?->toDateString(),
                    'period_end' => $item->period_end?->toDateString(),
                    'sort_order' => $item->sort_order,
                ]);
            }

            return $invoice->refresh();
        });
    }

    /* ===============================================================
     | Údržba
     |===============================================================*/

    /**
     * Prepne nezaplatené doklady po splatnosti do stavu „po splatnosti“.
     * Beží denne spolu s upomienkami.
     */
    public function markOverdue(): int
    {
        return Invoice::query()
            ->whereIn('status', [InvoiceStatus::Issued->value, InvoiceStatus::Sent->value])
            ->whereNotNull('due_at')
            ->whereDate('due_at', '<', today())
            ->update(['status' => InvoiceStatus::Overdue->value]);
    }

    /** @return array<string, mixed> */
    public function supplierSnapshot(): array
    {
        return config('invoicing.supplier');
    }

    protected function assertEditable(Invoice $invoice): void
    {
        if (! $invoice->isDraft()) {
            throw new DomainException('Vystavený doklad sa už nedá meniť. Vystav dobropis.');
        }
    }
}
