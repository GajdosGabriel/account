<?php

namespace App\Services\Messaging;

use App\Enums\ConversationStatus;
use App\Enums\MessageStatus;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Mailbox;
use App\Models\MailSuppression;
use Illuminate\Support\Facades\DB;
use ZBateson\MailMimeParser\Header\AddressHeader;
use ZBateson\MailMimeParser\IMessage;
use ZBateson\MailMimeParser\Message;
use ZBateson\MailMimeParser\Message\IMessagePart;

/**
 * Spracuje jeden prichádzajúci mail zo schránky projektu.
 *
 * Postup:
 *   1. správa o nedoručení (bounce) → označí pôvodnú správu, pri trvalej
 *      chybe zablokuje adresu, projekt dostane `message.bounced`;
 *   2. automatická odpoveď (dovolenka) → uloží sa, ale nikoho nebudí;
 *   3. odpoveď → spáruje sa s konverzáciou:
 *        In-Reply-To / References → X-Conversation → odosielateľ s jedinou
 *        konverzáciou → inak nepriradená (operátor ju priradí v inboxe).
 */
class InboundProcessor
{
    public const RESULT_STORED = 'stored';

    public const RESULT_UNASSIGNED = 'unassigned';

    public const RESULT_BOUNCE = 'bounce';

    public const RESULT_AUTO_REPLY = 'auto_reply';

    public const RESULT_SKIPPED = 'skipped';

    public function __construct(
        private ConversationService $conversations,
        private MessagingWebhooks $webhooks,
        private ReplyExtractor $extractor,
    ) {}

    public function process(Mailbox $mailbox, string $raw, ?int $uid = null): string
    {
        $mail = Message::from($raw, false);
        $messageId = MessageIds::normalize($mail->getHeaderValue('Message-ID'));
        $from = $this->address($mail, 'From');

        if ($this->alreadyStored($mailbox, $uid, $messageId)) {
            return self::RESULT_SKIPPED;
        }

        // Kópia vlastnej odoslanej správy (napr. Gmail ju dá aj do INBOX)
        if ($from && strcasecmp($from['email'], $mailbox->from_address) === 0) {
            return self::RESULT_SKIPPED;
        }

        if ($this->isBounce($mail, $from['email'] ?? '')) {
            return $this->handleBounce($mailbox, $mail, $raw);
        }

        return DB::transaction(fn () => $this->handleReply($mailbox, $mail, $messageId, $from, $uid));
    }

    /* ---------------------------------------------------------------
     | Odpovede
     |---------------------------------------------------------------*/

    /** @param array{email: string, name: ?string}|null $from */
    private function handleReply(Mailbox $mailbox, IMessage $mail, ?string $messageId, ?array $from, ?int $uid): string
    {
        $email = strtolower($from['email'] ?? 'neznamy@odosielatel');
        $autoReply = $this->isAutoReply($mail);
        $conversation = $this->match($mailbox, $mail, $email);
        // Aj odpoveď do všeobecnej konverzácie (operátor odpovedal pred
        // priradením) – projekt ju dostane až pri priradení.
        $unassigned = $conversation === null || ! $conversation->isAssigned();

        $conversation ??= $this->unassignedConversation($mailbox, $email, $from['name'] ?? null, $mail->getSubject());

        $html = $mail->getHtmlContent();
        $text = $mail->getTextContent() ?? ReplyExtractor::htmlToText($html);
        $reply = $mail->getTextContent() !== null
            ? $this->extractor->extract($text)
            : $this->extractor->extract(ReplyExtractor::htmlToText($html));

        /** @var ConversationMessage $message */
        $message = $conversation->messages()->create([
            'mailbox_id' => $mailbox->id,
            'direction' => ConversationMessage::IN,
            'source' => ConversationMessage::SOURCE_IMAP,
            'status' => $autoReply ? MessageStatus::AutoReply : MessageStatus::Received,
            'message_id' => $messageId,
            'in_reply_to' => MessageIds::normalize($mail->getHeaderValue('In-Reply-To')),
            'references' => implode(' ', MessageIds::parseList($this->rawHeader($mail, 'References'))) ?: null,
            'from_email' => $email,
            'from_name' => $from['name'] ?? null,
            'to_email' => $mailbox->from_address,
            'subject' => $mail->getSubject(),
            'body_html' => $html,
            'body_text' => $text,
            'reply_text' => $reply,
            'imap_uid' => $uid,
            'meta' => $unassigned || $autoReply ? null : ['notified' => true],
        ]);

        $this->storeAttachments($message, $mail);

        $conversation->forceFill(['last_inbound_at' => now(), 'last_message_at' => now()]);

        if (! $autoReply) {
            $conversation->forceFill([
                'status' => ConversationStatus::Open,
                'unread_count' => $conversation->unread_count + 1,
            ]);
        }

        $conversation->save();

        if ($autoReply) {
            return self::RESULT_AUTO_REPLY;
        }

        if ($unassigned) {
            return self::RESULT_UNASSIGNED;
        }

        $this->webhooks->dispatch('message.received', $message->setRelation('conversation', $conversation));

        return self::RESULT_STORED;
    }

    private function match(Mailbox $mailbox, IMessage $mail, string $email): ?Conversation
    {
        $ids = array_values(array_unique(array_filter([
            MessageIds::normalize($mail->getHeaderValue('In-Reply-To')),
            ...array_reverse(MessageIds::parseList($this->rawHeader($mail, 'References'))),
        ])));

        if ($ids !== []) {
            $known = ConversationMessage::query()
                ->whereIn('message_id', $ids)
                ->whereHas('conversation', fn ($q) => $q->where('product_id', $mailbox->product_id))
                ->with('conversation')
                ->get()
                ->sortBy(fn (ConversationMessage $m) => array_search($m->message_id, $ids, true))
                ->first();

            if ($known) {
                return $known->conversation;
            }

            // Odpoveď na mail, ktorý projekt poslal ešte sám (pred Accountom).
            // Všetky konverzácie s tým istým koreňom patria k jednej veci –
            // prednosť má tá s adresou odosielateľa, inak posledná živá.
            $byThread = Conversation::query()
                ->where('product_id', $mailbox->product_id)
                ->whereIn('thread_ref', $ids)
                ->whereNotNull('ref_type')
                ->orderByRaw('case when contact_email = ? then 0 else 1 end', [$email])
                ->orderByDesc('last_message_at')
                ->first();

            if ($byThread) {
                return $byThread;
            }
        }

        $header = trim((string) $mail->getHeaderValue('X-Conversation'));

        if ($header !== '') {
            $conversation = Conversation::query()
                ->where('product_id', $mailbox->product_id)
                ->where('uuid', $header)
                ->first();

            if ($conversation) {
                return $conversation;
            }
        }

        // Odpoveď z programu, ktorý nezachoval hlavičky: ak má odosielateľ
        // v projekte práve jednu konverzáciu, je to ona. Pri viacerých by
        // to bol tip – radšej nech rozhodne človek.
        $candidates = Conversation::query()
            ->where('product_id', $mailbox->product_id)
            ->where('contact_email', $email)
            ->whereNotNull('ref_type')
            ->limit(2)
            ->get();

        return $candidates->count() === 1 ? $candidates->first() : null;
    }

    private function unassignedConversation(Mailbox $mailbox, string $email, ?string $name, ?string $subject): Conversation
    {
        $conversation = Conversation::query()
            ->where('product_id', $mailbox->product_id)
            ->whereNull('ref_type')
            ->where('contact_email', $email)
            ->first();

        return $conversation ?? Conversation::create([
            'product_id' => $mailbox->product_id,
            'mailbox_id' => $mailbox->id,
            'contact_email' => $email,
            'contact_name' => $name,
            'subject' => $subject,
            'status' => ConversationStatus::Open,
        ]);
    }

    private function storeAttachments(ConversationMessage $message, IMessage $mail): void
    {
        $limit = (int) config('accounts.messaging.max_attachment_size');

        foreach ($mail->getAllAttachmentParts() as $part) {
            /** @var IMessagePart $part */
            $content = $part->getBinaryContentStream()?->getContents() ?? '';

            if ($content === '' || strlen($content) > $limit) {
                continue;
            }

            $this->conversations->storeAttachment(
                $message,
                $part->getFilename() ?: 'priloha',
                $part->getContentType(),
                $content,
            );
        }
    }

    /* ---------------------------------------------------------------
     | Nedoručené správy
     |---------------------------------------------------------------*/

    private function isBounce(IMessage $mail, string $from): bool
    {
        if (str_starts_with(strtolower((string) $mail->getContentType()), 'multipart/report')
            && stripos((string) $mail->getHeaderParameter('Content-Type', 'report-type'), 'delivery-status') !== false) {
            return true;
        }

        return preg_match('/^(mailer-daemon|postmaster)@/i', $from) === 1
            && preg_match('/(undeliver|delivery status|failure|nedoru[cč]|returned mail|unzustellbar)/iu', (string) $mail->getSubject()) === 1;
    }

    private function handleBounce(Mailbox $mailbox, IMessage $mail, string $raw): string
    {
        $original = $this->bouncedMessage($mailbox, $raw);
        $status = $this->dsnField($raw, 'Status');
        $recipient = $this->dsnRecipient($raw) ?? $original?->to_email;
        $hard = $status === null ? true : str_starts_with($status, '5');
        $reason = $this->dsnField($raw, 'Diagnostic-Code') ?? $mail->getSubject();

        if ($recipient && $hard) {
            $suppression = MailSuppression::firstOrNew([
                'product_id' => $mailbox->product_id,
                'email' => strtolower($recipient),
            ]);
            $suppression->reason = MailSuppression::HARD_BOUNCE;
            $suppression->bounce_count = $suppression->bounce_count + 1;
            $suppression->note = mb_substr((string) $reason, 0, 1000);
            $suppression->save();
        }

        if (! $original) {
            return self::RESULT_BOUNCE;
        }

        $original->forceFill([
            'status' => MessageStatus::Bounced,
            'bounce_type' => $hard ? 'hard' : 'soft',
            'error' => mb_substr(trim(($status ? $status.' ' : '').$reason), 0, 2000),
        ])->save();

        $this->webhooks->dispatch('message.bounced', $original);

        return self::RESULT_BOUNCE;
    }

    /**
     * Pôvodná správa podľa Message-ID v priloženej hlavičke. Bounce ju
     * nesie ako message/rfc822 alebo text/rfc822-headers – stačí nájsť
     * ktorékoľvek naše ID v surovom texte.
     */
    private function bouncedMessage(Mailbox $mailbox, string $raw): ?ConversationMessage
    {
        preg_match_all('/^(?:Message-ID|In-Reply-To|References)\s*:\s*(.+(?:\r?\n[ \t].+)*)/im', $raw, $matches);

        $ids = [];
        foreach ($matches[1] as $value) {
            array_push($ids, ...MessageIds::parseList($value));
        }

        if ($ids === []) {
            return null;
        }

        return ConversationMessage::query()
            ->where('direction', ConversationMessage::OUT)
            ->whereIn('message_id', array_unique($ids))
            ->whereHas('conversation', fn ($q) => $q->where('product_id', $mailbox->product_id))
            ->latest('id')
            ->first();
    }

    private function dsnRecipient(string $raw): ?string
    {
        if (preg_match('/^(?:Final|Original)-Recipient\s*:\s*rfc822\s*;\s*<?([^\s<>]+@[^\s<>]+)>?/im', $raw, $m)) {
            return strtolower($m[1]);
        }

        return null;
    }

    private function dsnField(string $raw, string $field): ?string
    {
        if (preg_match('/^'.preg_quote($field, '/').'\s*:\s*(.+(?:\r?\n[ \t].+)*)/im', $raw, $m)) {
            $value = trim(preg_replace('/\s+/', ' ', $m[1]) ?? $m[1]);

            if ($field === 'Status') {
                return preg_match('/\b([245]\.\d{1,3}\.\d{1,3})\b/', $value, $s) ? $s[1] : null;
            }

            return $value;
        }

        return null;
    }

    /* ---------------------------------------------------------------
     | Pomocné
     |---------------------------------------------------------------*/

    private function isAutoReply(IMessage $mail): bool
    {
        $auto = strtolower((string) $mail->getHeaderValue('Auto-Submitted'));

        if ($auto !== '' && $auto !== 'no') {
            return true;
        }

        if ($mail->getHeader('X-Autoreply') || $mail->getHeader('X-Autorespond')) {
            return true;
        }

        if (in_array(strtolower((string) $mail->getHeaderValue('Precedence')), ['auto_reply', 'bulk', 'junk'], true)) {
            return true;
        }

        return preg_match(
            '/^(automatic reply|auto(matic)?[- ]?reply|out of (the )?office|automatická odpoveď|automatická odpověď|abwesenheit|autoreply)/iu',
            trim((string) $mail->getSubject()),
        ) === 1;
    }

    /** @return array{email: string, name: ?string}|null */
    private function address(IMessage $mail, string $header): ?array
    {
        $value = $mail->getHeader($header);

        if (! $value instanceof AddressHeader) {
            return null;
        }

        $address = $value->getAddresses()[0] ?? null;

        if (! $address || ! filter_var($address->getEmail(), FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return ['email' => strtolower($address->getEmail()), 'name' => $address->getName() ?: null];
    }

    private function rawHeader(IMessage $mail, string $name): ?string
    {
        return $mail->getHeader($name)?->getRawValue();
    }

    private function alreadyStored(Mailbox $mailbox, ?int $uid, ?string $messageId): bool
    {
        if ($uid !== null && ConversationMessage::query()->where('mailbox_id', $mailbox->id)->where('imap_uid', $uid)->exists()) {
            return true;
        }

        return $messageId !== null && ConversationMessage::query()
            ->where('mailbox_id', $mailbox->id)
            ->where('direction', ConversationMessage::IN)
            ->where('message_id', $messageId)
            ->exists();
    }
}
