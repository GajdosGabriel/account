<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Udalosť o správe z Accountu (z webhooku).
 *
 *   message.received  klient odpovedal – `reply()` je text bez citácie
 *   message.sent      správa odišla (source = project | inbox)
 *   message.failed    správu sa nepodarilo odoslať
 *   message.bounced   správa sa vrátila ako nedoručiteľná
 *
 * Listener si vyfiltruje svoje veci podľa `refType()`:
 *
 *   Event::listen(function (AccountMessageEvent $e) {
 *       if ($e->refType() !== 'canal') return;
 *       $canal = Canal::find($e->refId());
 *       if (! $canal) return;   // vec medzičasom zmizla – Account to neopakuje
 *       ...
 *   });
 */
class AccountMessageEvent
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $conversation
     * @param  array<string, mixed>  $message
     */
    public function __construct(
        public readonly string $event,
        public readonly array $conversation,
        public readonly array $message,
    ) {}

    public function is(string $event): bool
    {
        return $this->event === $event;
    }

    public function refType(): ?string
    {
        return $this->conversation['ref']['type'] ?? null;
    }

    public function refId(): ?string
    {
        return $this->conversation['ref']['id'] ?? null;
    }

    public function email(): ?string
    {
        return $this->conversation['contact_email'] ?? null;
    }

    /** Text odpovede bez citácie pôvodnej správy. */
    public function reply(): string
    {
        return (string) ($this->message['reply_text'] ?? $this->message['text'] ?? '');
    }

    /** Odpísal operátor z inboxu Accountu (nie projekt sám). */
    public function fromInbox(): bool
    {
        return ($this->message['source'] ?? null) === 'inbox';
    }
}
