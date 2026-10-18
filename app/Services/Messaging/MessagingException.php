<?php

namespace App\Services\Messaging;

use RuntimeException;

/**
 * Správu nemožno prijať na odoslanie. `reason` je strojový kód pre API
 * (projekt podľa neho rozhodne, či skúsiť inú adresu), text je preložený.
 */
class MessagingException extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public static function suppressed(string $email): self
    {
        return new self('suppressed', __('messaging.errors.suppressed', ['email' => $email]));
    }

    public static function noMailbox(): self
    {
        return new self('no_mailbox', __('messaging.errors.no_mailbox'));
    }
}
