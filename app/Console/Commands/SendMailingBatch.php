<?php

namespace App\Console\Commands;

use App\Services\Mailing\MailingSender;
use Illuminate\Console\Command;

class SendMailingBatch extends Command
{
    protected $signature = 'mailing:send';

    protected $description = 'Odošle ďalšiu dávku e-mailov z kampaní (ponuky a automatizácie).';

    public function handle(MailingSender $sender): int
    {
        $count = $sender->runBatch();

        $this->info("Spracovaných {$count} doručení.");

        return self::SUCCESS;
    }
}
