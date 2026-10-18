<?php

namespace App\Services;

use RuntimeException;

/**
 * Account správu neprijal z dôvodu, ktorý opakovanie nevyrieši:
 *
 *   suppressed  adresa je v projekte blokovaná (trvalý bounce, odhlásenie)
 *   no_mailbox  projekt nemá v Accounte nastavenú schránku
 */
class AccountMessageRejected extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
