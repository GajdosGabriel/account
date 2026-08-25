<?php

namespace Database\Seeders;

use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Models\InvoiceNumberSeries;
use App\Models\Organization;
use App\Models\Plan;
use App\Services\Invoicing\InvoiceService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Testovacie doklady.
 *
 * Cieľ nie je „nejaké dáta“, ale všetky stavy, ktoré v praxi nastanú:
 * koncept, vystavená, odoslaná, uhradená, čiastočne uhradená, po splatnosti
 * s upomienkami, stornovaná, dobropis aj zálohová faktúra. Vďaka tomu sa
 * dá zoznam, filtre aj dropdown menu odskúšať bez klikania.
 */
class InvoiceSeeder extends Seeder
{
    public function run(): void
    {
        $this->series();

        $service = app(InvoiceService::class);

        $ukazka = Organization::where('ico', '31333532')->first();
        $mala = Organization::where('ico', '35815256')->first();
        $zahranicna = $this->foreignCustomer();

        if (! $ukazka) {
            $this->command?->warn('Chýbajú demo organizácie – spusti najprv DatabaseSeeder.');

            return;
        }

        if (Invoice::exists()) {
            $this->command?->line('Doklady už existujú, seed faktúr preskakujem.');

            return;
        }

        $plan = Plan::whereRelation('product', 'key', 'event')->where('key', 'standard')->first();

        /* ---------- 1. Uhradené faktúry za posledné tri mesiace ---------- */

        foreach ([3, 2, 1] as $monthsAgo) {
            $date = Carbon::today()->subMonths($monthsAgo)->startOfMonth();

            $draft = $this->build($service, $ukazka, InvoiceType::Invoice, $date, [
                [
                    'description' => 'Event – Standard',
                    'detail' => 'Mesačné predplatné',
                    'unit' => 'mesiac',
                    'unit_price' => 290000,   // 29,00 € v stotinách centa
                    'period_start' => $date->toDateString(),
                    'period_end' => $date->copy()->endOfMonth()->toDateString(),
                    'plan_id' => $plan?->id,
                    'product_id' => $plan?->product_id,
                ],
                [
                    'description' => 'Anonymizer – Team',
                    'detail' => 'Mesačné predplatné',
                    'unit' => 'mesiac',
                    'unit_price' => 490000,
                    'period_start' => $date->toDateString(),
                    'period_end' => $date->copy()->endOfMonth()->toDateString(),
                ],
            ]);

            $invoice = $service->issue($draft, $date);

            $invoice->forceFill([
                'status' => \App\Enums\InvoiceStatus::Sent,
                'sent_at' => $date->copy()->addHours(2),
                'sent_to' => $ukazka->billing_email,
                'sent_count' => 1,
            ])->save();

            $invoice->recordEvent('sent', 'Odoslané na '.$ukazka->billing_email.'.');

            $service->recordPayment($invoice->refresh(), null, $invoice->due_at->copy()->subDays(3));
        }

        /* ---------- 2. Po splatnosti s dvoma upomienkami ---------- */

        $overdue = $this->build($service, $ukazka, InvoiceType::Invoice, Carbon::today()->subDays(38), [
            [
                'description' => 'Samospráva – Business',
                'detail' => 'Mesačné predplatné',
                'unit' => 'mesiac',
                'unit_price' => 990000,
            ],
            [
                'description' => 'Migrácia dát',
                'detail' => 'Jednorazová služba, 4 hodiny',
                'quantity' => 4,
                'unit' => 'hod',
                'unit_price' => 650000,
            ],
        ]);

        $overdue = $service->issue($overdue, Carbon::today()->subDays(38));

        $overdue->forceFill([
            'status' => \App\Enums\InvoiceStatus::Overdue,
            'due_at' => Carbon::today()->subDays(24),
            'sent_at' => Carbon::today()->subDays(38),
            'sent_to' => $ukazka->billing_email,
            'sent_count' => 1,
            'reminder_count' => 2,
            'last_reminder_at' => Carbon::today()->subDays(4),
        ])->save();

        $overdue->recordEvent('reminded', 'Upomienka (friendly) odoslaná.', ['tone' => 'friendly']);
        $overdue->recordEvent('reminded', 'Upomienka (firm) odoslaná.', ['tone' => 'firm']);

        /* ---------- 3. Čiastočne uhradená ---------- */

        $partial = $this->build($service, $ukazka, InvoiceType::Invoice, Carbon::today()->subDays(12), [
            [
                'description' => 'Ročné predplatné Event – Pro',
                'unit' => 'rok',
                'unit_price' => 8690000,
                'discount_percent' => 8,
                'period_start' => Carbon::today()->subDays(12)->toDateString(),
                'period_end' => Carbon::today()->addYear()->subDays(13)->toDateString(),
            ],
        ]);

        $partial = $service->issue($partial, Carbon::today()->subDays(12));

        // Zákazník poslal polovicu – zvyšok po dohode do konca mesiaca.
        $service->recordPayment(
            $partial,
            (int) round($partial->total_cents / 2),
            Carbon::today()->subDays(5),
            'Čiastočná úhrada podľa dohody – zvyšok do konca mesiaca.',
        );

        /* ---------- 4. Vystavená, čaká na platbu ---------- */

        $open = $this->build($service, $mala ?? $ukazka, InvoiceType::Invoice, Carbon::today()->subDays(3), [
            [
                'description' => 'Event – Standard',
                'detail' => 'Prechod z Free na Standard',
                'unit' => 'mesiac',
                'unit_price' => 290000,
            ],
        ]);

        $service->issue($open, Carbon::today()->subDays(3));

        /* ---------- 5. Zálohová faktúra ---------- */

        $proforma = $this->build($service, $ukazka, InvoiceType::Proforma, Carbon::today()->subDay(), [
            [
                'description' => 'Záloha na implementáciu SSO',
                'detail' => '50 % z dohodnutej ceny 2 400 €',
                'unit' => 'ks',
                'unit_price' => 12_000_000,   // 1 200,00 €
            ],
        ]);

        $service->issue($proforma, Carbon::today()->subDay());

        /* ---------- 6. Dobropis k prvej uhradenej faktúre ---------- */

        $first = Invoice::ofType(InvoiceType::Invoice)->orderBy('id')->first();

        if ($first) {
            $service->creditNote($first, [$first->items->last()->id], 'Zákazník projekt nevyužíval, vraciame pomernú časť.');
        }

        /* ---------- 7. Stornovaná ---------- */

        $void = $this->build($service, $ukazka, InvoiceType::Invoice, Carbon::today()->subDays(20), [
            ['description' => 'Chybne vystavená položka', 'unit' => 'ks', 'unit_price' => 100000],
        ]);

        $void = $service->issue($void, Carbon::today()->subDays(20));
        $service->cancel($void, 'Vystavené omylom na nesprávnu firmu.');

        /* ---------- 8. Rozpracovaný koncept ---------- */

        $draft = $service->draft($ukazka, InvoiceType::Invoice);

        $service->addItem($draft, [
            'description' => 'Event – Standard',
            'detail' => 'Nasledujúce obdobie',
            'unit' => 'mesiac',
            'unit_price' => 290000,
            'period_start' => Carbon::today()->addMonth()->startOfMonth()->toDateString(),
            'period_end' => Carbon::today()->addMonth()->endOfMonth()->toDateString(),
        ]);

        /* ---------- 9. Zahraničná faktúra – reverse charge ---------- */

        if ($zahranicna) {
            $eu = $this->build($service, $zahranicna, InvoiceType::Invoice, Carbon::today()->subDays(6), [
                [
                    'description' => 'Anonymizer – Team',
                    'detail' => 'Monthly subscription',
                    'unit' => 'mesiac',
                    'unit_price' => 490000,
                ],
            ]);

            $eu = $service->issue($eu, Carbon::today()->subDays(6));

            $eu->forceFill([
                'status' => \App\Enums\InvoiceStatus::Sent,
                'sent_at' => now()->subDays(6),
                'sent_to' => $zahranicna->billing_email,
                'sent_count' => 1,
            ])->save();
        }

        $this->command?->newLine();
        $this->command?->info('Faktúry: '.Invoice::withTrashed()->count().' dokladov vo všetkých stavoch.');
        $this->command?->line('  Vrátane dobropisu, zálohovej faktúry, storna a EÚ faktúry s prenesením daňovej povinnosti.');
    }

    /**
     * Číselné rady. Formát 2026NNNN je najbežnejší – účtovníci ho poznajú
     * a z čísla sa dá odvodiť variabilný symbol bez ďalších pravidiel.
     */
    protected function series(): void
    {
        $definitions = [
            ['key' => 'faktura', 'name' => 'Odoslané faktúry', 'type' => InvoiceType::Invoice, 'pattern' => '{YYYY}{SEQ}'],
            ['key' => 'zaloha', 'name' => 'Zálohové faktúry', 'type' => InvoiceType::Proforma, 'pattern' => '{YYYY}9{SEQ}'],
            ['key' => 'dobropis', 'name' => 'Dobropisy', 'type' => InvoiceType::CreditNote, 'pattern' => '{YYYY}8{SEQ}'],
        ];

        foreach ($definitions as $definition) {
            InvoiceNumberSeries::updateOrCreate(
                ['key' => $definition['key']],
                [
                    'name' => $definition['name'],
                    'document_type' => $definition['type'],
                    'pattern' => $definition['pattern'],
                    'sequence_length' => 4,
                    'reset_period' => 'year',
                    'is_default' => true,
                ],
            );
        }
    }

    /** Nemecký zákazník s platným IČ DPH – ukážka reverse charge. */
    protected function foreignCustomer(): ?Organization
    {
        return Organization::firstOrCreate(
            ['ico' => 'HRB-889012'],
            [
                'name' => 'Muster GmbH',
                'legal_name' => 'Muster Software GmbH',
                'legal_form' => 'ine',
                'ic_dph' => 'DE123456789',
                'vat_mode' => 'payer',
                'street' => 'Musterstraße',
                'street_no' => '12',
                'city' => 'Berlin',
                'postal_code' => '10115',
                'country' => 'DE',
                'email' => 'info@muster.de',
                'billing_email' => 'rechnung@muster.de',
                'currency' => 'EUR',
                'payment_terms_days' => 30,
                'invoice_language' => 'de',
            ],
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function build(
        InvoiceService $service,
        Organization $organization,
        InvoiceType $type,
        Carbon $issuedAt,
        array $items,
    ): Invoice {
        $dueDays = $organization->payment_terms_days ?: 14;

        $invoice = $service->draft($organization, $type, [
            'issued_at' => $issuedAt->toDateString(),
            'delivered_at' => $issuedAt->toDateString(),
            'due_at' => $issuedAt->copy()->addDays($dueDays)->toDateString(),
        ]);

        foreach ($items as $item) {
            $service->addItem($invoice, $item);
        }

        return $invoice->refresh();
    }
}
