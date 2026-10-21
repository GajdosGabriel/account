<?php

namespace App\Services\Messaging;

use App\Enums\ConversationStatus;
use App\Enums\MessageStatus;
use App\Jobs\SendConversationMessage;
use App\Models\Conversation;
use App\Models\ConversationAttachment;
use App\Models\ConversationMessage;
use App\Models\Mailbox;
use App\Models\MailSuppression;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Konverzácie a odchádzajúce správy.
 *
 * Jediné miesto, ktoré zakladá konverzácie a mení ich stav – API, inbox
 * aj príjem pošty idú cez neho, takže pravidlá „kto je na ťahu“ sú
 * na jednom mieste.
 */
class ConversationService
{
    public function __construct(private MessagingWebhooks $webhooks) {}

    /**
     * Správa z projektu (API).
     *
     * @param  array{
     *     to: string, to_name?: ?string, subject: string, html?: ?string, text?: ?string,
     *     ref: array{type: string, id: string|int, label?: ?string, url?: ?string},
     *     kind?: ?string, organization?: ?Organization, mailbox?: ?string,
     *     conversation?: ?string, thread_id?: ?string, meta?: ?array<string, mixed>,
     *     attachments?: array<int, array{filename: string, mime?: ?string, content: string}>
     * }  $data
     */
    public function sendFromProject(Product $product, array $data): ConversationMessage
    {
        $email = strtolower(trim($data['to']));

        if (MailSuppression::blocks($product, $email)) {
            throw MessagingException::suppressed($email);
        }

        $mailbox = $this->resolveMailbox($product, $data['mailbox'] ?? null);

        return DB::transaction(function () use ($product, $data, $email, $mailbox) {
            $conversation = $this->findOrOpen($product, $mailbox, $email, $data);

            $message = $this->queue($conversation, $mailbox, [
                'source' => ConversationMessage::SOURCE_PROJECT,
                'kind' => $data['kind'] ?? null,
                'to_email' => $email,
                'subject' => $data['subject'],
                'body_html' => $data['html'] ?? null,
                'body_text' => $data['text'] ?? ReplyExtractor::htmlToText($data['html'] ?? null),
                'meta' => $data['meta'] ?? null,
            ], $data['attachments'] ?? []);

            $conversation->forceFill([
                'status' => ConversationStatus::Waiting,
                'subject' => $conversation->subject ?? $data['subject'],
            ])->save();

            return $message;
        });
    }

    /** Odpoveď operátora z inboxu. Píše sa obyčajný text. */
    public function reply(Conversation $conversation, User $operator, string $text, ?string $subject = null): ConversationMessage
    {
        $mailbox = $conversation->mailbox ?? $this->resolveMailbox($conversation->product, null);

        if (MailSuppression::blocks($conversation->product, $conversation->contact_email)) {
            throw MessagingException::suppressed($conversation->contact_email);
        }

        return DB::transaction(function () use ($conversation, $operator, $text, $subject, $mailbox) {
            $message = $this->queue($conversation, $mailbox, [
                'source' => ConversationMessage::SOURCE_INBOX,
                'kind' => 'inbox.reply',
                'to_email' => $conversation->contact_email,
                'subject' => $subject ?: $this->replySubject($conversation),
                'body_html' => $this->textToHtml($text),
                'body_text' => $text,
                'sent_by_user_id' => $operator->id,
            ]);

            $conversation->forceFill([
                'status' => ConversationStatus::Answered,
                'unread_count' => 0,
            ])->save();

            $conversation->messages()->where('direction', ConversationMessage::IN)->whereNull('read_at')
                ->update(['read_at' => now()]);

            return $message;
        });
    }

    /**
     * Konverzácia k veci v projekte. Ak projekt pošle `conversation`,
     * pokračuje sa v nej (napr. po zmene adresy klienta).
     *
     * @param  array<string, mixed>  $data
     */
    public function findOrOpen(Product $product, Mailbox $mailbox, string $email, array $data): Conversation
    {
        $ref = $data['ref'];
        $attributes = array_filter([
            'ref_label' => $ref['label'] ?? null,
            'ref_url' => $ref['url'] ?? null,
            'thread_ref' => MessageIds::normalize($data['thread_id'] ?? null),
            'contact_name' => $data['to_name'] ?? null,
            'organization_id' => ($data['organization'] ?? null)?->id,
        ], fn ($value) => $value !== null);

        $conversation = null;

        if (! empty($data['conversation'])) {
            $conversation = Conversation::query()
                ->where('product_id', $product->id)
                ->where('uuid', $data['conversation'])
                ->first();

            // Odpoveďou na nepriradený mail si ho projekt priradí k svojej
            // veci (objednávka z e-mailu) – ďalšie odpovede klienta už dostane.
            if ($conversation && ! $conversation->isAssigned()) {
                $conversation->forceFill(['ref_type' => (string) $ref['type'], 'ref_id' => (string) $ref['id']]);
            }
        }

        $conversation ??= Conversation::query()->firstOrNew([
            'product_id' => $product->id,
            'ref_type' => (string) $ref['type'],
            'ref_id' => (string) $ref['id'],
            'contact_email' => $email,
        ]);

        $conversation->fill($attributes);
        $conversation->mailbox_id ??= $mailbox->id;
        $conversation->status ??= ConversationStatus::Waiting;
        $conversation->save();

        return $conversation;
    }

    /**
     * Uloží odchádzajúcu správu a zaradí ju na odoslanie.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array{filename: string, mime?: ?string, content: string}>  $attachments
     */
    public function queue(Conversation $conversation, Mailbox $mailbox, array $attributes, array $attachments = []): ConversationMessage
    {
        $threadIds = $conversation->exists ? $conversation->threadMessageIds() : [];

        /** @var ConversationMessage $message */
        $message = $conversation->messages()->create([
            ...$attributes,
            'mailbox_id' => $mailbox->id,
            'direction' => ConversationMessage::OUT,
            'status' => MessageStatus::Queued,
            'message_id' => MessageIds::generate($mailbox),
            'in_reply_to' => $threadIds === [] ? null : end($threadIds),
            'references' => $threadIds === [] ? null : implode(' ', array_slice($threadIds, -20)),
            'from_email' => $mailbox->from_address,
            'from_name' => $mailbox->from_name,
            'next_attempt_at' => now(),
        ]);

        foreach ($attachments as $file) {
            $this->storeAttachment($message, $file['filename'], $file['mime'] ?? null, $file['content']);
        }

        $conversation->forceFill([
            'last_outbound_at' => now(),
            'last_message_at' => now(),
        ])->save();

        SendConversationMessage::dispatch($message->id)->afterCommit();

        return $message;
    }

    public function storeAttachment(ConversationMessage $message, string $filename, ?string $mime, string $contents): ConversationAttachment
    {
        $disk = (string) config('accounts.messaging.attachments_disk');
        $uuid = (string) Str::uuid();
        $safe = Str::limit(preg_replace('/[^\w.\-]+/u', '_', $filename) ?: 'priloha', 150, '');
        $path = 'messaging/'.$message->conversation->uuid.'/'.$uuid.'/'.$safe;

        Storage::disk($disk)->put($path, $contents);

        return $message->attachments()->create([
            'uuid' => $uuid,
            'filename' => $filename,
            'mime' => $mime,
            'size' => strlen($contents),
            'disk' => $disk,
            'path' => $path,
        ]);
    }

    public function changeStatus(Conversation $conversation, ConversationStatus $status): void
    {
        $conversation->forceFill(['status' => $status])->save();
    }

    public function markRead(Conversation $conversation): void
    {
        if ($conversation->unread_count === 0) {
            return;
        }

        $conversation->messages()->where('direction', ConversationMessage::IN)->whereNull('read_at')
            ->update(['read_at' => now()]);
        $conversation->forceFill(['unread_count' => 0])->save();
    }

    /**
     * Nepriradenú konverzáciu zlúči do konverzácie k veci v projekte.
     * Projekt potom dostane prijaté správy ako `message.received`.
     * Presúvajú sa aj odpovede operátora – na všeobecnú správu sa dá
     * odpovedať ešte pred priradením.
     */
    public function mergeInto(Conversation $source, Conversation $target): Conversation
    {
        abort_if($source->is($target), 422);
        abort_unless($source->product_id === $target->product_id, 422);

        DB::transaction(function () use ($source, $target) {
            $source->messages()->update(['conversation_id' => $target->id]);

            $target->forceFill([
                'unread_count' => $target->unread_count + $source->unread_count,
                'last_inbound_at' => max($target->last_inbound_at, $source->last_inbound_at),
                'last_message_at' => max($target->last_message_at, $source->last_message_at),
                'status' => $source->unread_count > 0 ? ConversationStatus::Open : $target->status,
            ])->save();

            $source->delete();
        });

        $target->refresh();

        $target->messages()
            ->where('direction', ConversationMessage::IN)
            ->where('status', MessageStatus::Received)
            ->where(fn ($q) => $q->whereNull('meta->notified')->orWhere('meta->notified', false))
            ->get()
            ->each(function (ConversationMessage $message) {
                $message->forceFill(['meta' => [...($message->meta ?? []), 'notified' => true]])->save();
                $this->webhooks->dispatch('message.received', $message);
            });

        return $target;
    }

    private function resolveMailbox(Product $product, ?string $address): Mailbox
    {
        $mailbox = $address
            ? Mailbox::query()->where('product_id', $product->id)->active()
                ->where('from_address', strtolower(trim($address)))->first()
            : null;

        $mailbox ??= Mailbox::defaultFor($product);

        return $mailbox ?? throw MessagingException::noMailbox();
    }

    private function replySubject(Conversation $conversation): string
    {
        $subject = (string) ($conversation->messages()->latest('id')->value('subject') ?? $conversation->subject);

        return preg_match('/^(re|aw|odp|odpoveď)\s*:/iu', $subject) ? $subject : 'Re: '.$subject;
    }

    private function textToHtml(string $text): string
    {
        return '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.5">'
            .nl2br(e($text))
            .'</div>';
    }
}
