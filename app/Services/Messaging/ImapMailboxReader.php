<?php

namespace App\Services\Messaging;

use App\Models\Mailbox;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\Message;

/**
 * Číta schránku cez IMAP (Gmail, Websupport, Office 365…).
 *
 * Správy sa neoznačujú ako prečítané a nepresúvajú sa – schránka ostáva
 * použiteľná aj v bežnom poštovom programe. Kde sme skončili, drží
 * `mailboxes.imap_last_uid`.
 */
class ImapMailboxReader implements MailboxReader
{
    public function fetch(Mailbox $mailbox, int $afterUid, int $limit): iterable
    {
        $client = $this->connect($mailbox);

        try {
            $messages = $this->folder($client, $mailbox)
                ->query()
                ->leaveUnread()
                ->setFetchOrderAsc()
                ->limit($limit)
                ->getByUidGreater($afterUid);

            $count = 0;

            /** @var Message $message */
            foreach ($messages as $message) {
                if ($count++ >= $limit) {
                    break;
                }

                yield [
                    'uid' => (int) $message->getUid(),
                    'raw' => rtrim($message->getHeader()->raw)."\r\n\r\n".$message->getRawBody(),
                ];
            }
        } finally {
            $client->disconnect();
        }
    }

    public function latestUid(Mailbox $mailbox): int
    {
        $client = $this->connect($mailbox);

        try {
            // UIDNEXT je UID, ktoré dostane najbližšia nová správa.
            $status = $client->getConnection()
                ->examineFolder($mailbox->imap_folder ?: 'INBOX')
                ->validatedData();

            return max(0, (int) ($status['uidnext'] ?? 1) - 1);
        } finally {
            $client->disconnect();
        }
    }

    public function test(Mailbox $mailbox): void
    {
        $client = $this->connect($mailbox);
        $this->folder($client, $mailbox);
        $client->disconnect();
    }

    private function connect(Mailbox $mailbox): Client
    {
        $encryption = $mailbox->imap_encryption ?: 'ssl';

        $client = (new ClientManager(['options' => ['fetch_order' => 'asc']]))->make([
            'host' => $mailbox->imap_host,
            'port' => $mailbox->imap_port ?: ($encryption === 'ssl' ? 993 : 143),
            'encryption' => $encryption === 'none' ? false : $encryption,
            'validate_cert' => true,
            'username' => $mailbox->imap_username,
            'password' => $mailbox->imap_password,
            'protocol' => 'imap',
            'timeout' => (int) config('accounts.messaging.imap_timeout', 30),
        ]);

        $client->connect();

        return $client;
    }

    private function folder(Client $client, Mailbox $mailbox): Folder
    {
        $name = $mailbox->imap_folder ?: 'INBOX';

        return $client->getFolderByPath($name)
            ?? throw new \RuntimeException(__('messaging.errors.folder_missing', ['folder' => $name]));
    }
}
