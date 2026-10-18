<?php

namespace App\Jobs;

use App\Enums\MessageStatus;
use App\Models\ConversationMessage;
use App\Services\Messaging\MessagingWebhooks;
use App\Services\Messaging\OutboundMailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Odoslanie správy z konverzácie.
 *
 * Chyba sa nevyhadzuje ďalej – správa ostane vo fronte s časom ďalšieho
 * pokusu a `messaging:retry` ju zaradí znova (rovnako ako webhooky).
 * Pri synchrónnej fronte by výnimka inak zhodila API požiadavku projektu.
 */
class SendConversationMessage implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $messageId) {}

    public function handle(OutboundMailer $mailer, MessagingWebhooks $webhooks): void
    {
        $message = ConversationMessage::with(['conversation.product', 'mailbox', 'attachments'])->find($this->messageId);

        if (! $message || $message->status !== MessageStatus::Queued) {
            return;
        }

        $message->increment('attempts');

        try {
            $mailer->send($message);
        } catch (\Throwable $e) {
            $this->scheduleRetry($message, $e, $webhooks);

            return;
        }

        $message->forceFill([
            'status' => MessageStatus::Sent,
            'sent_at' => now(),
            'error' => null,
            'next_attempt_at' => null,
        ])->save();

        $webhooks->dispatch('message.sent', $message);
    }

    private function scheduleRetry(ConversationMessage $message, \Throwable $e, MessagingWebhooks $webhooks): void
    {
        $max = (int) config('accounts.messaging.max_send_attempts');
        $message->error = mb_substr($e->getMessage(), 0, 2000);

        if ($message->attempts >= $max) {
            $message->forceFill(['status' => MessageStatus::Failed, 'next_attempt_at' => null])->save();
            $webhooks->dispatch('message.failed', $message);

            return;
        }

        $backoff = config('accounts.messaging.send_backoff');
        $delay = $backoff[min($message->attempts - 1, count($backoff) - 1)] ?? 3600;

        $message->forceFill(['next_attempt_at' => now()->addSeconds($delay)])->save();
    }
}
