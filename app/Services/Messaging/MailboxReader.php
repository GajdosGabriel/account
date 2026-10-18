<?php

namespace App\Services\Messaging;

use App\Models\Mailbox;

/**
 * Zdroj prichádzajúcej pošty. Oddelený od spracovania, aby sa dal
 * v testoch nahradiť fixtúrami a neskôr prípadne inbound webhookom.
 */
interface MailboxReader
{
    /**
     * Správy s UID väčším ako $afterUid, zoradené vzostupne.
     *
     * @return iterable<int, array{uid: int, raw: string}>
     */
    public function fetch(Mailbox $mailbox, int $afterUid, int $limit): iterable;

    /** Najvyššie UID v priečinku – pri prvom spustení sa staršia pošta preskočí. */
    public function latestUid(Mailbox $mailbox): int;

    /** Overí prihlásenie; pri chybe vyhodí výnimku so zrozumiteľnou správou. */
    public function test(Mailbox $mailbox): void;
}
