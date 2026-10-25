<?php

namespace App\Console\Commands;

use App\Enums\ConversationStatus;
use App\Models\Conversation;
use App\Services\Messaging\ConversationService;
use Illuminate\Console\Command;

class PurgeSpamConversations extends Command
{
    protected $signature = 'messaging:purge-spam';

    protected $description = 'Zmaže spam, do ktorého dlhšie neprišla žiadna správa (aj s prílohami).';

    public function handle(ConversationService $conversations): int
    {
        $cutoff = now()->subDays((int) config('accounts.messaging.spam_retention_days'));
        $count = 0;

        Conversation::query()
            ->where('status', ConversationStatus::Spam)
            ->where(fn ($q) => $q->where('last_message_at', '<', $cutoff)
                ->orWhere(fn ($w) => $w->whereNull('last_message_at')->where('updated_at', '<', $cutoff)))
            ->lazyById()
            ->each(function (Conversation $conversation) use ($conversations, &$count) {
                $conversations->destroy($conversation);
                $count++;
            });

        $this->info("Zmazaných {$count} konverzácií zo Spamu.");

        return self::SUCCESS;
    }
}
