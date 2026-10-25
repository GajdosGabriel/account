<?php

namespace App\Enums;

/**
 * Stav konverzácie z pohľadu operátora: kto je na ťahu.
 */
enum ConversationStatus: string
{
    /** Klient odpovedal a čaká sa na nás. */
    case Open = 'open';
    /** Poslali sme správu z projektu a čakáme na klienta. */
    case Waiting = 'waiting';
    /** Odpísali sme z inboxu. */
    case Answered = 'answered';
    /** Vybavené – nová odpoveď konverzáciu znova otvorí. */
    case Closed = 'closed';
    /** Nevyžiadaná pošta – skrytá z inboxu, po čase sa zmaže. */
    case Spam = 'spam';

    public function label(): string
    {
        return __('enums.conversation_status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'amber',
            self::Waiting => 'sky',
            self::Answered => 'emerald',
            self::Closed => 'slate',
            self::Spam => 'rose',
        };
    }
}
