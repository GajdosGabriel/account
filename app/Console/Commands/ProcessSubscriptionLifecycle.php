<?php

namespace App\Console\Commands;

use App\Services\Billing\SubscriptionManager;
use Illuminate\Console\Command;

class ProcessSubscriptionLifecycle extends Command
{
    protected $signature = 'subscriptions:lifecycle';

    protected $description = 'Posunie predplatné podľa uplynutých lehôt (trial, grace, suspension).';

    public function handle(SubscriptionManager $manager): int
    {
        $counts = $manager->processLifecycle();

        $this->table(
            ['Prechod', 'Počet'],
            collect($counts)->map(fn ($count, $key) => [$key, $count])->values()->all(),
        );

        return self::SUCCESS;
    }
}
