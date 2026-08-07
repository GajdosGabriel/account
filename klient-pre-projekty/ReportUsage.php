<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Zaznam;
use App\Services\AccountClient;
use Illuminate\Console\Command;

/**
 * Denné hlásenie spotreby do Accountu.
 *
 * Naplánuj v routes/console.php:
 *   Schedule::command(ReportUsage::class)->dailyAt('02:00');
 *
 * Kľúče metrík musia sedieť s katalógom funkcií daného projektu
 * (Account → Projekty → katalóg, stĺpec „metrika spotreby").
 */
class ReportUsage extends Command
{
    protected $signature = 'account:report-usage';

    protected $description = 'Nahlási spotrebu do Accountu';

    public function handle(AccountClient $account): int
    {
        // Zoskupíme podľa firmy – v projekte je organization_id na users.
        $organizationIds = User::query()
            ->whereNotNull('organization_id')
            ->distinct()
            ->pluck('organization_id');

        foreach ($organizationIds as $organizationId) {
            $account->reportUsage($organizationId, [
                'users' => User::where('organization_id', $organizationId)->count(),
                'records' => Zaznam::where('organization_id', $organizationId)->count(),
            ]);
        }

        $this->info("Nahlásených {$organizationIds->count()} firiem.");

        return self::SUCCESS;
    }
}
