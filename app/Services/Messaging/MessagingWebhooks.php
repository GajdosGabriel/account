<?php

namespace App\Services\Messaging;

use App\Models\Conversation;
use App\Models\ConversationAttachment;
use App\Models\ConversationMessage;
use App\Services\Webhooks\WebhookDispatcher;

/**
 * Udalosti o správach smerom do projektov.
 *
 * Posielajú sa len projektu, ktorému konverzácia patrí, a len pri
 * priradenej konverzácii – nepriradenú správu projekt nevie kam dať.
 * Po priradení v inboxe sa udalosť pošle dodatočne.
 *
 *   message.received  klient odpovedal
 *   message.sent      správa odišla (z projektu aj z inboxu – rozlíši `source`)
 *   message.failed    správu sa nepodarilo odoslať ani po opakovaní
 *   message.bounced   prišla správa o nedoručení
 */
class MessagingWebhooks
{
    public const EVENTS = ['message.received', 'message.sent', 'message.failed', 'message.bounced'];

    public function __construct(private WebhookDispatcher $dispatcher) {}

    public function dispatch(string $event, ConversationMessage $message): void
    {
        $conversation = $message->conversation;

        if (! $conversation?->isAssigned()) {
            return;
        }

        $this->dispatcher->dispatch($event, self::payload($conversation, $message), $conversation->product);
    }

    /** @return array<string, mixed> */
    public static function payload(Conversation $conversation, ConversationMessage $message): array
    {
        return [
            'conversation' => self::conversation($conversation),
            'message' => self::message($message),
        ];
    }

    /** @return array<string, mixed> */
    public static function conversation(Conversation $conversation): array
    {
        return [
            'id' => $conversation->uuid,
            'ref' => $conversation->ref(),
            'contact_email' => $conversation->contact_email,
            'contact_name' => $conversation->contact_name,
            'organization_id' => $conversation->organization?->uuid,
            'subject' => $conversation->subject,
            'status' => $conversation->status->value,
            'mailbox' => $conversation->mailbox?->from_address,
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function message(ConversationMessage $message): array
    {
        return [
            'id' => $message->uuid,
            'direction' => $message->direction,
            'source' => $message->source,
            'kind' => $message->kind,
            'status' => $message->status->value,
            'from' => $message->from_email,
            'from_name' => $message->from_name,
            'to' => $message->to_email,
            'subject' => $message->subject,
            'text' => $message->body_text,
            'reply_text' => $message->reply_text,
            'html' => $message->body_html,
            'bounce_type' => $message->bounce_type,
            'error' => $message->error,
            'sent_by' => $message->sender?->name,
            'meta' => $message->meta,
            'attachments' => $message->attachments->map(fn (ConversationAttachment $a) => [
                'id' => $a->uuid,
                'filename' => $a->filename,
                'mime' => $a->mime,
                'size' => $a->size,
                'url' => $a->temporaryUrl(),
            ])->values()->all(),
            'sent_at' => $message->sent_at?->toIso8601String(),
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }
}
