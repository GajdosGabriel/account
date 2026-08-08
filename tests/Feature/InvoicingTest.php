<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Mail\InvoiceMail;
use App\Models\Invoice;
use App\Models\InvoiceNumberSeries;
use App\Models\Organization;
use App\Models\User;
use App\Services\Invoicing\InvoiceMailer;
use App\Services\Invoicing\InvoiceNumberGenerator;
use App\Services\Invoicing\InvoiceService;
use App\Services\Invoicing\VatResolver;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InvoicingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->series();
    }

    /* ===============================================================
     | Číslovanie
     |===============================================================*/

    public function test_cislo_sa_prideli_az_pri_vystaveni(): void
    {
        $invoice = $this->draft();

        $this->assertNull($invoice->number);
        $this->assertTrue($invoice->isDraft());

        $issued = app(InvoiceService::class)->issue($invoice);

        $this->assertSame(date('Y').'0001', $issued->number);
        $this->assertSame(InvoiceStatus::Issued, $issued->status);
    }

    public function test_cisla_iducu_za_sebou_bez_dier(): void
    {
        $service = app(InvoiceService::class);

        $numbers = collect(range(1, 3))
            ->map(fn () => $service->issue($this->draft())->number)
            ->all();

        $this->assertSame(
            [date('Y').'0001', date('Y').'0002', date('Y').'0003'],
            $numbers,
        );
    }

    public function test_zmazany_koncept_nespotrebuje_cislo(): void
    {
        $service = app(InvoiceService::class);

        $this->draft()->delete();

        $this->assertSame(date('Y').'0001', $service->issue($this->draft())->number);
    }

    public function test_rad_sa_v_novom_roku_zacina_od_jednotky(): void
    {
        $generator = app(InvoiceNumberGenerator::class);

        $first = $generator->next(InvoiceType::Invoice, Carbon::parse('2026-12-20'));
        $next = $generator->next(InvoiceType::Invoice, Carbon::parse('2027-01-03'));

        $this->assertSame('20260001', $first['number']);
        $this->assertSame('20270001', $next['number']);
    }

    public function test_variabilny_symbol_vychadza_z_cisla_dokladu(): void
    {
        $issued = app(InvoiceService::class)->issue($this->draft());

        $this->assertSame($issued->number, $issued->variable_symbol);
    }

    /* ===============================================================
     | Sumy a DPH
     |===============================================================*/

    public function test_polozky_sa_scitaju_aj_so_zlavou(): void
    {
        $service = app(InvoiceService::class);
        $invoice = $service->draft($this->organization());

        // 2 × 100 € so zľavou 10 % = 180 € základ, DPH 23 % = 41,40 €
        $service->addItem($invoice, [
            'description' => 'Predplatné',
            'quantity' => 2,
            'unit_price' => 1_000_000,
            'discount_percent' => 10,
            'vat_rate' => 23,
        ]);

        $invoice->refresh();

        $this->assertSame(18000, $invoice->subtotal_cents);
        $this->assertSame(4140, $invoice->vat_cents);
        $this->assertSame(22140, $invoice->total_cents);
    }

    public function test_rekapitulacia_dph_zoskupuje_sadzby(): void
    {
        $service = app(InvoiceService::class);
        $invoice = $service->draft($this->organization());

        $service->addItem($invoice, ['description' => 'A', 'unit_price' => 1_000_000, 'vat_rate' => 23]);
        $service->addItem($invoice, ['description' => 'B', 'unit_price' => 500_000, 'vat_rate' => 23]);
        $service->addItem($invoice, ['description' => 'C', 'unit_price' => 200_000, 'vat_rate' => 5]);

        $summary = collect($invoice->refresh()->vat_summary)->keyBy('rate');

        $this->assertCount(2, $summary);
        $this->assertSame(15000, $summary[23.0]['base_cents']);
        $this->assertSame(3450, $summary[23.0]['vat_cents']);
        $this->assertSame(2000, $summary[5.0]['base_cents']);
    }

    public function test_slovensky_odberatel_dostane_dph(): void
    {
        $vat = app(VatResolver::class)->resolve($this->organization());

        $this->assertSame(23.0, $vat['rate']);
        $this->assertFalse($vat['reverse_charge']);
    }

    public function test_eu_firma_s_ic_dph_ma_prenesenie_danovej_povinnosti(): void
    {
        $organization = $this->organization([
            'country' => 'DE',
            'ic_dph' => 'DE123456789',
            'vat_mode' => 'payer',
        ]);

        $vat = app(VatResolver::class)->resolve($organization);

        $this->assertSame(0.0, $vat['rate']);
        $this->assertTrue($vat['reverse_charge']);
        $this->assertStringContainsString('§ 15', $vat['note']);
    }

    public function test_odberatel_mimo_eu_je_bez_dph(): void
    {
        $vat = app(VatResolver::class)->resolve($this->organization(['country' => 'US']));

        $this->assertSame(0.0, $vat['rate']);
        $this->assertFalse($vat['reverse_charge']);
        $this->assertSame('export', $vat['reason']);
    }

    /* ===============================================================
     | Nemennosť vystaveného dokladu
     |===============================================================*/

    public function test_vystaveny_doklad_sa_neda_menit(): void
    {
        $service = app(InvoiceService::class);
        $issued = $service->issue($this->draft());

        $this->expectException(DomainException::class);

        $service->addItem($issued, ['description' => 'Prilepené dodatočne', 'unit_price' => 100]);
    }

    public function test_fakturacne_udaje_sa_odfotia_pri_vystaveni(): void
    {
        // Na doklad ide obchodné meno, nie prezývka firmy – preto sa
        // nastavuje `legal_name`. Bez neho by test porovnával s náhodnou
        // hodnotou z factory a padol by na správnom správaní.
        $organization = $this->organization([
            'name' => 'Pôvodná firma',
            'legal_name' => 'Pôvodná firma, s. r. o.',
        ]);
        $issued = app(InvoiceService::class)->issue($this->draft($organization));

        $organization->update(['legal_name' => 'Nový názov, s. r. o.', 'city' => 'Košice']);

        $snapshot = $issued->refresh()->billing_snapshot;

        $this->assertSame('Pôvodná firma, s. r. o.', $snapshot['name']);
        $this->assertNotSame('Košice', $snapshot['address']['city']);
    }

    public function test_bez_kompletnych_udajov_sa_neda_vystavit(): void
    {
        $organization = $this->organization(['ico' => null]);

        $this->expectException(DomainException::class);

        app(InvoiceService::class)->issue($this->draft($organization));
    }

    /* ===============================================================
     | Úhrady, storno, dobropis
     |===============================================================*/

    public function test_ciastocna_uhrada_nechava_doklad_otvoreny(): void
    {
        $service = app(InvoiceService::class);
        $issued = $service->issue($this->draft());

        // Doklad je na 35,67 € – zákazník poslal 20 €.
        $service->recordPayment($issued, 2000);

        $issued->refresh();

        $this->assertSame(InvoiceStatus::PartiallyPaid, $issued->status);
        $this->assertSame(2000, $issued->paid_cents);
        $this->assertSame($issued->total_cents - 2000, $issued->outstandingCents());
    }

    public function test_plna_uhrada_uzavrie_doklad(): void
    {
        $service = app(InvoiceService::class);
        $issued = $service->issue($this->draft());

        $service->recordPayment($issued);

        $this->assertTrue($issued->refresh()->isPaid());
        $this->assertSame(0, $issued->outstandingCents());
    }

    public function test_uhradeny_doklad_sa_neda_stornovat(): void
    {
        $service = app(InvoiceService::class);
        $issued = $service->issue($this->draft());
        $service->recordPayment($issued);

        $this->expectException(DomainException::class);

        $service->cancel($issued->refresh());
    }

    public function test_dobropis_ma_zaporne_sumy_a_vlastny_rad(): void
    {
        $service = app(InvoiceService::class);
        $issued = $service->issue($this->draft());

        $credit = $service->creditNote($issued);

        $this->assertSame(InvoiceType::CreditNote, $credit->type);
        $this->assertSame($issued->id, $credit->parent_invoice_id);
        $this->assertSame(-$issued->total_cents, $credit->total_cents);
        $this->assertSame(date('Y').'80001', $credit->number);
    }

    public function test_dobropis_sa_da_vystavit_len_k_vystavenej_fakture(): void
    {
        $this->expectException(DomainException::class);

        app(InvoiceService::class)->creditNote($this->draft());
    }

    public function test_zalohova_faktura_sa_prevedie_na_riadnu(): void
    {
        $service = app(InvoiceService::class);

        $proforma = $service->issue($this->draft(type: InvoiceType::Proforma));
        $invoice = $service->invoiceFromProforma($proforma);

        $this->assertSame(InvoiceType::Invoice, $invoice->type);
        $this->assertTrue($invoice->isDraft());
        $this->assertSame($proforma->total_cents, $invoice->total_cents);
        $this->assertCount(1, $invoice->items);
    }

    /* ===============================================================
     | Po splatnosti
     |===============================================================*/

    public function test_doklad_po_splatnosti_zmeni_stav(): void
    {
        $service = app(InvoiceService::class);
        $issued = $service->issue($this->draft());

        $issued->forceFill(['due_at' => Carbon::today()->subDays(5)])->save();

        $service->markOverdue();

        $this->assertSame(InvoiceStatus::Overdue, $issued->refresh()->status);
        $this->assertSame(5, $issued->daysOverdue());
    }

    public function test_odoslanie_zapise_udalost_a_prijemcu(): void
    {
        Mail::fake();

        $issued = app(InvoiceService::class)->issue($this->draft());

        app(InvoiceMailer::class)->send($issued);

        Mail::assertQueued(InvoiceMail::class);

        $issued->refresh();

        $this->assertSame(InvoiceStatus::Sent, $issued->status);
        $this->assertSame(1, $issued->sent_count);
        $this->assertTrue($issued->events->contains('event', 'sent'));
    }

    /* ===============================================================
     | Policy
     |===============================================================*/

    public function test_policy_povoli_upravu_len_konceptu(): void
    {
        $user = User::factory()->create();
        $service = app(InvoiceService::class);

        $draft = $this->draft();

        $this->assertTrue($user->can('update', $draft));
        $this->assertTrue($user->can('delete', $draft));

        $issued = $service->issue($draft);

        $this->assertFalse($user->can('update', $issued));
        $this->assertFalse($user->can('delete', $issued));
        $this->assertTrue($user->can('credit', $issued));
    }

    public function test_policy_nepovoli_dva_dobropisy_k_jednej_fakture(): void
    {
        $user = User::factory()->create();
        $service = app(InvoiceService::class);

        $issued = $service->issue($this->draft());
        $service->creditNote($issued);

        $this->assertFalse($user->can('credit', $issued->refresh()));
    }

    public function test_policy_nedovoli_natrvalo_zmazat_vystaveny_doklad(): void
    {
        $user = User::factory()->create();

        $issued = app(InvoiceService::class)->issue($this->draft());
        $issued->delete();

        $this->assertFalse($user->can('forceDelete', $issued));
    }

    public function test_zmazany_koncept_sa_da_obnovit(): void
    {
        $user = User::factory()->create();
        $draft = $this->draft();

        $draft->delete();

        $this->assertSoftDeleted($draft);
        $this->assertTrue($user->can('restore', $draft));

        $draft->restore();

        $this->assertNotSoftDeleted($draft);
    }

    /* ===============================================================
     | HTTP
     |===============================================================*/

    public function test_zoznam_faktur_je_dostupny_prihlasenemu_operatorovi(): void
    {
        $issued = app(InvoiceService::class)->issue($this->draft());

        $this->actingAs(User::factory()->create())
            ->get('/invoices')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Invoices/Index')
                ->has('invoices.data', 1)
                ->where('invoices.data.0.number', $issued->number));
    }

    public function test_vystavenie_cez_http_pridi_cislo(): void
    {
        $draft = $this->draft();

        $this->actingAs(User::factory()->create())
            ->post("/invoices/{$draft->id}/issue")
            ->assertRedirect();

        $this->assertNotNull($draft->refresh()->number);
    }

    public function test_vystaveny_doklad_sa_cez_http_neda_zmazat(): void
    {
        $issued = app(InvoiceService::class)->issue($this->draft());

        $this->actingAs(User::factory()->create())
            ->delete("/invoices/{$issued->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('invoices', ['id' => $issued->id, 'deleted_at' => null]);
    }

    /* ===============================================================
     | Pomocné
     |===============================================================*/

    protected function series(): void
    {
        $definitions = [
            ['faktura', 'Odoslané faktúry', InvoiceType::Invoice, '{YYYY}{SEQ}'],
            ['zaloha', 'Zálohové faktúry', InvoiceType::Proforma, '{YYYY}9{SEQ}'],
            ['dobropis', 'Dobropisy', InvoiceType::CreditNote, '{YYYY}8{SEQ}'],
        ];

        foreach ($definitions as [$key, $name, $type, $pattern]) {
            InvoiceNumberSeries::create([
                'key' => $key,
                'name' => $name,
                'document_type' => $type,
                'pattern' => $pattern,
                'sequence_length' => 4,
                'reset_period' => 'year',
                'is_default' => true,
            ]);
        }
    }

    /** @param array<string, mixed> $attributes */
    protected function organization(array $attributes = []): Organization
    {
        return Organization::factory()->create($attributes);
    }

    protected function draft(?Organization $organization = null, InvoiceType $type = InvoiceType::Invoice): Invoice
    {
        $service = app(InvoiceService::class);

        $invoice = $service->draft($organization ?? $this->organization(), $type);

        $service->addItem($invoice, [
            'description' => 'Predplatné Projekt 1 – Standard',
            'quantity' => 1,
            'unit' => 'mesiac',
            'unit_price' => 290_000,   // 29,00 €
            'vat_rate' => 23,
        ]);

        return $invoice->refresh();
    }
}
