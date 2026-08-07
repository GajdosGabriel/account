<?php

namespace App\Console\Commands;

use App\Jobs\DeliverWebhook;
use App\Models\WebhookDelivery;
use Illuminate\Console\Command;

class RetryWebhookDeliveries extends Command
{
    protected $signature = 'webhooks:retry';

    protected $description = 'Znovu zaradí webhooky, ktorým uplynul čas ďalšieho pokusu.';

    public function handle(): int
    {
        $count = 0;

        WebhookDelivery::query()
            ->whereNull('delivered_at')
            ->whereNull('failed_at')
            ->whereNotNull('next_attempt_at')
            ->where('next_attempt_at', '<=', now())
            ->each(function (WebhookDelivery $delivery) use (&$count) {
                DeliverWebhook::dispatch($delivery->id);
                $count++;
            });

        $this->info("Zaradených {$count} webhookov.");

        return self::SUCCESS;
    }
}
