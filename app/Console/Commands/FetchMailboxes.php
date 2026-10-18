<?php

namespace App\Console\Commands;

use App\Models\Mailbox;
use App\Services\Messaging\InboundProcessor;
use App\Services\Messaging\MailboxReader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Vyzdvihne novú poštu zo schránok projektov.
 *
 * Pri prvom spustení schránky sa stará pošta preskočí (zapamätá sa
 * najvyššie UID), inak by sa do inboxu nahrali roky histórie. Kto ju
 * chce, spustí príkaz s `--backfill`.
 */
class FetchMailboxes extends Command
{
    protected $signature = 'messaging:fetch
        {--mailbox= : ID jednej schránky}
        {--backfill : pri prvom spustení načítať aj staršiu poštu}';

    protected $description = 'Načíta prichádzajúce maily zo schránok projektov a spáruje ich do konverzácií.';

    public function handle(MailboxReader $reader, InboundProcessor $processor): int
    {
        $mailboxes = Mailbox::query()
            ->active()
            ->whereNotNull('imap_host')
            ->when($this->option('mailbox'), fn ($q, $id) => $q->whereKey($id))
            ->get()
            ->filter(fn (Mailbox $mailbox) => $mailbox->readsInbound());

        $failed = false;

        foreach ($mailboxes as $mailbox) {
            try {
                $this->fetch($mailbox, $reader, $processor);
            } catch (\Throwable $e) {
                $failed = true;
                $mailbox->forceFill(['imap_checked_at' => now(), 'imap_error' => mb_substr($e->getMessage(), 0, 2000)])->save();
                Log::warning('messaging.fetch_failed', ['mailbox' => $mailbox->id, 'error' => $e->getMessage()]);
                $this->error("{$mailbox->from_address}: {$e->getMessage()}");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function fetch(Mailbox $mailbox, MailboxReader $reader, InboundProcessor $processor): void
    {
        if ($mailbox->imap_last_uid === null && ! $this->option('backfill')) {
            $mailbox->forceFill([
                'imap_last_uid' => $reader->latestUid($mailbox),
                'imap_checked_at' => now(),
                'imap_error' => null,
            ])->save();

            $this->line("{$mailbox->from_address}: prvé spustenie, staršia pošta preskočená.");

            return;
        }

        $stats = [];

        foreach ($reader->fetch($mailbox, (int) $mailbox->imap_last_uid, (int) config('accounts.messaging.fetch_batch')) as $item) {
            try {
                $result = $processor->process($mailbox, $item['raw'], $item['uid']);
            } catch (\Throwable $e) {
                // Jedna pokazená správa nesmie zaseknúť celú schránku.
                Log::error('messaging.process_failed', ['mailbox' => $mailbox->id, 'uid' => $item['uid'], 'error' => $e->getMessage()]);
                $result = 'error';
            }

            $stats[$result] = ($stats[$result] ?? 0) + 1;
            $mailbox->forceFill(['imap_last_uid' => $item['uid']])->save();
        }

        $mailbox->forceFill(['imap_checked_at' => now(), 'imap_error' => null])->save();

        $summary = collect($stats)->map(fn ($count, $key) => "$key: $count")->implode(', ') ?: 'nič nové';
        $this->line("{$mailbox->from_address}: {$summary}");
    }
}
