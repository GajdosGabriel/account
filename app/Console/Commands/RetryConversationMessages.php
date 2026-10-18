<?php

namespace App\Console\Commands;

use App\Enums\MessageStatus;
use App\Jobs\SendConversationMessage;
use App\Models\ConversationMessage;
use Illuminate\Console\Command;

class RetryConversationMessages extends Command
{
    protected $signature = 'messaging:retry';

    protected $description = 'Znovu zaradí správy, ktoré sa nepodarilo odoslať a uplynul im čas ďalšieho pokusu.';

    public function handle(): int
    {
        $count = 0;

        ConversationMessage::query()
            ->where('status', MessageStatus::Queued)
            ->where('attempts', '>', 0)
            ->whereNotNull('next_attempt_at')
            ->where('next_attempt_at', '<=', now())
            ->each(function (ConversationMessage $message) use (&$count) {
                SendConversationMessage::dispatch($message->id);
                $count++;
            });

        $this->info("Zaradených {$count} správ.");

        return self::SUCCESS;
    }
}
