<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\SubscriptionStatus;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Services\Invoicing\InvoiceStatistics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Čísla na prehľade.
 *
 * Štatistika, ktorú nikto neoveril, je len graf – tu je pre každý údaj
 * jeden prípad, ktorý ho vie vyvrátiť.
 */
class InvoiceStatisticsTest extends TestCase
{
    use RefreshDatabase;

    protected InvoiceStatistics $stats;

    protected Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stats = app(InvoiceStatistics::class);
        $this->organization = Organization::create(['name' => 'Skúšobná firma', 'status' => 'active']);
    }

    /** @param array<string, mixed> $attributes */
    protected function invoice(array $attributes = []): Invoice
    {
        return Invoice::create([
            'organization_id' => $this->organization->id,
            // Číslo je v schéme povinné aj pre koncept – viď poznámku v teste nižšie.
            'number' => 'F'.str_pad((string) (Invoice::withTrashed()->count() + 1), 6, '0', STR_PAD_LEFT),
            'type' => InvoiceType::Invoice->value,
            'status' => InvoiceStatus::Issued->value,
            'currency' => 'EUR',
            'issued_at' => now()->toDateString(),
            'due_at' => now()->addDays(14)->toDateString(),
            'total_cents' => 12000,
            'paid_cents' => 0,
            ...$attributes,
        ]);
    }

    public function test_vyfakturovane_tento_mesiac_scita_len_tento_mesiac(): void
    {
        $this->invoice(['total_cents' => 10000]);
        $this->invoice(['total_cents' => 5000]);
        $this->invoice(['total_cents' => 99900, 'issued_at' => now()->subMonths(2)->toDateString()]);

        $summary = $this->stats->summary();

        $this->assertSame(15000, $summary['invoiced_month']['cents']);
        $this->assertSame(2, $summary['invoiced_month']['count']);
    }

    public function test_dobropis_znizi_vyfakturovanu_sumu(): void
    {
        $this->invoice(['total_cents' => 10000]);
        $this->invoice(['type' => InvoiceType::CreditNote->value, 'total_cents' => -4000]);

        $this->assertSame(6000, $this->stats->summary()['invoiced_month']['cents']);
    }

    public function test_zalohova_faktura_sa_do_trzieb_nerata(): void
    {
        $this->invoice(['total_cents' => 10000]);
        $this->invoice(['type' => InvoiceType::Proforma->value, 'total_cents' => 50000]);

        // Zálohová sa neskôr mení na riadnu – inak by ten istý obchod
        // v tržbách figuroval dvakrát.
        $this->assertSame(10000, $this->stats->summary()['invoiced_month']['cents']);
    }

    public function test_koncept_a_storno_sa_do_trzieb_neratajú(): void
    {
        $this->invoice(['total_cents' => 10000]);
        $this->invoice(['status' => InvoiceStatus::Draft->value, 'total_cents' => 70000]);
        $this->invoice(['status' => InvoiceStatus::Cancelled->value, 'total_cents' => 80000]);

        $summary = $this->stats->summary();

        $this->assertSame(10000, $summary['invoiced_month']['cents']);
        $this->assertSame(1, $summary['drafts']);
    }

    public function test_neuhradene_rataju_len_zostatok(): void
    {
        $this->invoice(['total_cents' => 10000, 'paid_cents' => 4000, 'status' => InvoiceStatus::PartiallyPaid->value]);
        $this->invoice(['total_cents' => 6000]);
        $this->invoice(['total_cents' => 90000, 'paid_cents' => 90000, 'status' => InvoiceStatus::Paid->value]);

        $summary = $this->stats->summary();

        $this->assertSame(12000, $summary['outstanding']['cents']);
        $this->assertSame(2, $summary['outstanding']['count']);
    }

    public function test_po_splatnosti_je_len_to_co_ma_termin_za_sebou(): void
    {
        $this->invoice(['total_cents' => 7000, 'due_at' => now()->subDay()->toDateString()]);
        $this->invoice(['total_cents' => 5000, 'due_at' => now()->addDay()->toDateString()]);

        $summary = $this->stats->summary();

        $this->assertSame(7000, $summary['overdue']['cents']);
        $this->assertSame(1, $summary['overdue']['count']);
    }

    public function test_prognoza_scita_splatne_faktury_a_obnovy(): void
    {
        // splatné v okne
        $this->invoice(['total_cents' => 10000, 'due_at' => now()->addDays(10)->toDateString()]);
        // až za oknom
        $this->invoice(['total_cents' => 30000, 'due_at' => now()->addDays(60)->toDateString()]);
        // po splatnosti – riziko, nie prognóza
        $this->invoice(['total_cents' => 4000, 'due_at' => now()->subDays(3)->toDateString()]);

        $product = Product::create(['key' => 'projekt', 'name' => 'Projekt']);
        $plan = Plan::create([
            'product_id' => $product->id, 'key' => 'standard', 'name' => 'Standard',
            'price_cents' => 2900, 'interval' => 'month', 'trial_days' => 0,
        ]);

        Subscription::create([
            'organization_id' => $this->organization->id,
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active->value,
            'quantity' => 2,
            'current_period_end' => now()->addDays(7),
        ]);

        $forecast = $this->stats->forecast(30);

        $this->assertSame(10000, $forecast['due']['cents']);
        $this->assertSame(5800, $forecast['renewals']['cents']);
        $this->assertSame(15800, $forecast['total_cents']);
        $this->assertSame(4000, $forecast['at_risk']['cents']);
    }

    public function test_vyvoj_zaradi_doklad_do_mesiaca_vystavenia_a_uhrady(): void
    {
        $this->invoice([
            'total_cents' => 10000,
            'paid_cents' => 10000,
            'status' => InvoiceStatus::Paid->value,
            'issued_at' => now()->subMonth()->startOfMonth()->toDateString(),
            'paid_at' => now()->startOfMonth(),
        ]);

        $months = collect($this->stats->months(6))->keyBy('key');

        $previous = now()->subMonth()->format('Y-m');
        $current = now()->format('Y-m');

        $this->assertSame(10000, $months[$previous]['invoiced_cents']);
        $this->assertSame(0, $months[$previous]['paid_cents']);
        $this->assertSame(0, $months[$current]['invoiced_cents']);
        $this->assertSame(10000, $months[$current]['paid_cents']);
        $this->assertCount(6, $months);
    }
}
