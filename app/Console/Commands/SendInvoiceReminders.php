<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\Invoicing\InvoiceMailer;
use App\Services\Invoicing\InvoiceService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Denná údržba pohľadávok.
 *
 *   php artisan invoices:remind
 *   php artisan invoices:remind --dry-run
 */
class SendInvoiceReminders extends Command
{
    protected $signature = 'invoices:remind
                            {--dry-run : Len vypíše, čo by sa odoslalo}';

    protected $description = 'Označí faktúry po splatnosti a rozošle upomienky.';

    public function handle(InvoiceService $invoices, InvoiceMailer $mailer): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $marked = $invoices->markOverdue();

        if ($marked) {
            $this->line("Po splatnosti: {$marked} dokladov prešlo do stavu „po splatnosti“.");
        }

        if (! config('invoicing.reminders.enabled')) {
            $this->warn('Upomienky sú vypnuté (Faktúry → Nastavenia alebo INVOICE_REMINDERS_ENABLED).');

            return self::SUCCESS;
        }

        if ($mailer->mode() === 'off') {
            $this->warn('Odosielanie dokladov je vypnuté (Faktúry → Nastavenia alebo INVOICE_SENDING_MODE).');

            return self::SUCCESS;
        }

        if ($mailer->testing()) {
            $this->warn('Testovací režim – upomienky idú na '.(config('invoicing.sending.redirect_to') ?: '(nenastavenú adresu)').', nie zákazníkom.');
        }

        $schedule = config('invoicing.reminders.schedule', []);
        $max = (int) config('invoicing.reminders.max', 3);
        $minimum = (int) config('invoicing.reminders.min_outstanding_cents', 0);

        krsort($schedule); // od najprísnejšej úrovne – aby sa nepreskočila

        $sent = 0;
        $failed = 0;

        Invoice::query()
            ->overdue()
            ->with(['organization', 'items'])
            ->each(function (Invoice $invoice) use ($schedule, $max, $minimum, $dryRun, $mailer, &$sent, &$failed) {
                if ($invoice->reminder_count >= $max) {
                    return;
                }

                // Vypnuté odosielanie, firma s „Doklady neposielať“…
                if ($mailer->blockedReason($invoice) !== null) {
                    return;
                }

                // Nedoplatok pod hranicou sa nevymáha.
                if ($minimum > 0 && $invoice->outstandingCents() < $minimum) {
                    return;
                }

                // Nikdy dve upomienky v jeden deň.
                if ($invoice->last_reminder_at?->isToday()) {
                    return;
                }

                $days = $invoice->daysOverdue();
                $level = 0;
                $tone = null;

                foreach ($schedule as $threshold => $candidate) {
                    if ($days >= $threshold) {
                        $tone = $candidate;
                        $level = count($schedule) - array_search($threshold, array_keys($schedule), true);
                        break;
                    }
                }

                if (! $tone) {
                    return;
                }

                // Táto úroveň už bola odoslaná.
                if ($invoice->reminder_count >= $level) {
                    return;
                }

                $this->line(sprintf(
                    '  %s  %-14s  %s dní po splatnosti  →  %s',
                    $dryRun ? '[dry]' : '  →  ',
                    $invoice->number,
                    $days,
                    $tone,
                ));

                if ($dryRun) {
                    return;
                }

                try {
                    $mailer->remind($invoice, $tone);
                    $sent++;
                } catch (Throwable $e) {
                    $failed++;
                    $this->error("  {$invoice->number}: {$e->getMessage()}");
                }
            });

        $this->newLine();
        $this->info($dryRun
            ? 'Skúšobný beh – nič sa neodoslalo.'
            : "Odoslaných upomienok: {$sent}".($failed ? ", zlyhalo: {$failed}" : ''));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
