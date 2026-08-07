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
            $this->warn('Upomienky sú vypnuté (invoicing.reminders.enabled).');

            return self::SUCCESS;
        }

        $schedule = config('invoicing.reminders.schedule', []);
        $max = (int) config('invoicing.reminders.max', 3);

        krsort($schedule); // od najprísnejšej úrovne – aby sa nepreskočila

        $sent = 0;
        $failed = 0;

        Invoice::query()
            ->overdue()
            ->with(['organization', 'items'])
            ->each(function (Invoice $invoice) use ($schedule, $max, $dryRun, $mailer, &$sent, &$failed) {
                if ($invoice->reminder_count >= $max) {
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
