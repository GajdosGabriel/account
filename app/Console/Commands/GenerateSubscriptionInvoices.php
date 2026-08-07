<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Services\Invoicing\InvoiceMailer;
use App\Services\Invoicing\InvoiceService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Automatická fakturácia predplatného.
 *
 *   php artisan invoices:generate            # koncepty na obdobia, ktoré čoskoro končia
 *   php artisan invoices:generate --issue    # rovno vystaviť
 *   php artisan invoices:generate --send     # vystaviť a odoslať
 *   php artisan invoices:generate --dry-run
 *
 * Predvolene vzniká len KONCEPT. Faktúra, ktorá odíde zákazníkovi bez toho,
 * aby sa na ňu niekto pozrel, je najrýchlejšia cesta k trápnemu dobropisu.
 */
class GenerateSubscriptionInvoices extends Command
{
    protected $signature = 'invoices:generate
                            {--issue : Vystaviť koncepty rovno (pridelí čísla)}
                            {--send : Vystaviť a odoslať zákazníkom}
                            {--days= : Koľko dní dopredu fakturovať}
                            {--dry-run : Len vypíše, čo by vzniklo}';

    protected $description = 'Vystaví faktúry za predplatné, ktorým končí zúčtovacie obdobie.';

    public function handle(InvoiceService $invoices, InvoiceMailer $mailer): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $send = (bool) $this->option('send');
        $issue = $send || $this->option('issue') || config('invoicing.billing_run.auto_issue');

        $days = (int) ($this->option('days') ?? config('invoicing.billing_run.issue_days_before', 7));
        $horizon = Carbon::today()->addDays($days);

        $candidates = Subscription::query()
            ->whereIn('status', [
                SubscriptionStatus::Active->value,
                SubscriptionStatus::PastDue->value,
            ])
            ->whereNotNull('current_period_end')
            ->whereDate('current_period_end', '<=', $horizon)
            ->with(['organization', 'plan.product'])
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('Nič na fakturáciu – žiadne obdobie nekončí do '.$horizon->format('j. n. Y').'.');

            return self::SUCCESS;
        }

        $created = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($candidates as $subscription) {
            $organization = $subscription->organization;
            $plan = $subscription->plan;

            if (! $plan) {
                $this->warn("  {$organization->name}: predplatné bez plánu, preskakujem.");
                $skipped++;

                continue;
            }

            if ($plan->price_cents <= 0 && config('invoicing.billing_run.skip_zero')) {
                $skipped++;

                continue;
            }

            $periodStart = $subscription->current_period_end?->copy() ?? Carbon::today();
            $periodEnd = $plan->interval === 'year'
                ? $periodStart->copy()->addYear()->subDay()
                : $periodStart->copy()->addMonth()->subDay();

            // Idempotencia – to isté obdobie nikdy dvakrát.
            $exists = Invoice::where('subscription_id', $subscription->id)
                ->whereHas('items', fn ($q) => $q->whereDate('period_start', $periodStart->toDateString()))
                ->exists();

            if ($exists) {
                $skipped++;

                continue;
            }

            if ($missing = $organization->missingBillingFields()) {
                $this->warn("  {$organization->name}: chýba ".implode(', ', $missing).' – preskakujem.');
                $skipped++;

                continue;
            }

            $this->line(sprintf(
                '  %s %-28s %-22s %s',
                $dryRun ? '[dry]' : '  +  ',
                mb_strimwidth($organization->name, 0, 28),
                mb_strimwidth($plan->product->name.' / '.$plan->name, 0, 22),
                $periodStart->format('j. n.').' – '.$periodEnd->format('j. n. Y'),
            ));

            if ($dryRun) {
                $created++;

                continue;
            }

            try {
                $invoice = $invoices->draftForSubscription($subscription, $periodStart, $periodEnd);

                if ($issue) {
                    $invoice = $invoices->issue($invoice);
                }

                if ($send) {
                    $mailer->send($invoice);
                }

                $created++;
            } catch (Throwable $e) {
                $failed++;
                $this->error("  {$organization->name}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info(sprintf(
            '%s: %d, preskočené: %d%s',
            $dryRun ? 'Vzniklo by dokladov' : ($issue ? 'Vystavených faktúr' : 'Vytvorených konceptov'),
            $created,
            $skipped,
            $failed ? ", zlyhalo: {$failed}" : '',
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
