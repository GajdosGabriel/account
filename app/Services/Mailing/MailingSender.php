<?php

namespace App\Services\Mailing;

use App\Models\ConversationMessage;
use App\Models\Mailbox;
use App\Models\MailingCampaign;
use App\Models\MailingContact;
use App\Models\MailingDelivery;
use App\Models\MailingTrigger;
use App\Models\MailSuppression;
use App\Models\Product;
use App\Services\Messaging\ConversationService;
use App\Services\Messaging\MessageIds;
use App\Services\Messaging\MessagingException;
use App\Services\Messaging\OutboundMailer;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

/**
 * Odosielanie kampaní po dávkach z plánovača.
 *
 * Riadok v databáze je globálny zámok (aj pri viacerých serveroch).
 * Pokus o odoslanie cez SMTP sa automaticky neopakuje – prerušený pokus
 * ostane viditeľný ako „sending“, aby zákazník nedostal mail dvakrát.
 */
class MailingSender
{
    public function __construct(
        private MailingRenderer $renderer,
        private MailingMeasurement $measurement,
        private OutboundMailer $outbound,
        private ConversationService $conversations,
        private BounceClassifier $classifier,
        private MailingWebhooks $webhooks,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('accounts.mailing.enabled');
    }

    public function dryRun(): bool
    {
        return (bool) config('accounts.mailing.dry_run');
    }

    public function runBatch(): int
    {
        if (! $this->enabled()) {
            return 0;
        }

        $count = 0;

        for ($i = 0; $i < (int) config('accounts.mailing.batch_size'); $i++) {
            $delivery = $this->claim();

            if (! $delivery) {
                break;
            }

            $this->process($delivery);
            $count++;
        }

        return $count;
    }

    /** Ďalšie doručenie na rade, už prepnuté do stavu „sending“. */
    private function claim(): ?MailingDelivery
    {
        return DB::transaction(function () {
            $mutex = DB::table('mailing_campaigns')->orderBy('id')->lockForUpdate()->first();

            if (! $mutex) {
                return null;
            }

            // Limit platí pre projekt – poskytovateľ SMTP obmedzuje odosielateľa.
            $full = DB::table('mailing_deliveries as d')
                ->join('mailing_campaigns as c', 'c.id', '=', 'd.campaign_id')
                ->where('d.attempted_at', '>=', now()->subHour())
                ->groupBy('c.product_id')
                ->havingRaw('COUNT(*) >= ?', [(int) config('accounts.mailing.hourly_limit')])
                ->pluck('c.product_id');

            $delivery = MailingDelivery::query()
                ->select('mailing_deliveries.*')
                ->join('mailing_campaigns as c', 'c.id', '=', 'mailing_deliveries.campaign_id')
                ->where('mailing_deliveries.status', MailingDelivery::PENDING)
                ->where('c.status', MailingCampaign::QUEUED)
                ->where('c.scheduled_at', '<=', now())
                ->where(fn ($q) => $q->whereNull('mailing_deliveries.due_at')->orWhere('mailing_deliveries.due_at', '<=', now()))
                ->when($full->isNotEmpty(), fn ($q) => $q->whereNotIn('c.product_id', $full))
                ->orderBy('mailing_deliveries.id')
                ->first();

            if (! $delivery) {
                return null;
            }

            $claimed = MailingDelivery::query()->whereKey($delivery->id)->where('status', MailingDelivery::PENDING)
                ->update(['status' => MailingDelivery::SENDING, 'attempted_at' => now(), 'updated_at' => now()]);

            return $claimed ? $delivery->fresh() : null;
        });
    }

    private function process(MailingDelivery $delivery): void
    {
        $campaign = $delivery->campaign()->with(['product', 'mailbox', 'layout'])->first();
        $contact = $delivery->contact;

        $skip = $this->skipReason($campaign, $delivery, $contact);

        if ($skip !== null) {
            $delivery->forceFill(['status' => MailingDelivery::SKIPPED, 'error' => $skip])->save();
            $this->complete($campaign);

            return;
        }

        try {
            [$subject, $html] = $campaign->isReply()
                ? $this->sendReply($campaign, $delivery)
                : $this->sendOffer($campaign, $delivery, $contact);

            $delivery->forceFill(['status' => MailingDelivery::SENT, 'sent_at' => now(), 'error' => null])->save();

            if ($contact?->soft_bounces) {
                $contact->forceFill(['soft_bounces' => 0])->save();
            }

            $this->webhooks->delivery('mailing.delivery.sent', $delivery, $subject, $html);
        } catch (MessagingException $e) {
            $delivery->forceFill([
                'status' => $e->reason === 'suppressed' ? MailingDelivery::SKIPPED : MailingDelivery::FAILED,
                'error' => mb_substr($e->getMessage(), 0, 255),
            ])->save();

            if ($delivery->status === MailingDelivery::FAILED) {
                $this->webhooks->delivery('mailing.delivery.failed', $delivery);
            }
        } catch (\Throwable $e) {
            report($e);
            $bounce = $this->classifier->classify($e->getMessage());
            $bounced = $bounce['type'] !== BounceClassifier::NONE
                && $this->recordBounce($campaign->product, $delivery->email, $bounce['type'], $bounce['reason']);

            $delivery->forceFill([
                'status' => $bounced ? MailingDelivery::BOUNCED : MailingDelivery::FAILED,
                'error' => $bounce['type'] !== BounceClassifier::NONE ? $bounce['reason'] : __('mailing.errors.send_failed'),
            ])->save();

            $this->webhooks->delivery($bounced ? 'mailing.delivery.bounced' : 'mailing.delivery.failed', $delivery);
        }

        $this->complete($campaign);
    }

    private function skipReason(MailingCampaign $campaign, MailingDelivery $delivery, ?MailingContact $contact): ?string
    {
        if ($campaign->status !== MailingCampaign::QUEUED) {
            return __('mailing.errors.campaign_stopped');
        }

        if ($campaign->isReply()) {
            // Marketingové odhlásenie sa na servisnú odpoveď nevzťahuje, blokovaná adresa áno.
            return match (true) {
                ! $delivery->ref_type => __('mailing.errors.reply_without_ref'),
                MailSuppression::blocks($campaign->product, $delivery->email) => __('mailing.errors.suppressed'),
                default => null,
            };
        }

        return match (true) {
            ! $contact || ! $contact->isReachable() => __('mailing.errors.unreachable'),
            $campaign->isAutomation() && $campaign->coupon_code && $campaign->couponExpired() => __('mailing.errors.coupon_expired'),
            default => null,
        };
    }

    /** @return array{0: string, 1: string} */
    private function sendOffer(MailingCampaign $campaign, MailingDelivery $delivery, MailingContact $contact): array
    {
        $mailbox = $this->mailbox($campaign);
        $unsubscribe = MailingMeasurement::publicUrl('mailing.unsubscribe', $contact->token);
        $variables = $delivery->variables();
        $subject = MailingRenderer::fillText((string) $campaign->subject, $variables);
        $html = $this->measurement->decorate(
            $this->renderer->render($campaign, $contact, $variables, $unsubscribe),
            $campaign, $delivery, $unsubscribe,
        );

        $delivery->forceFill(['message_id' => MessageIds::generate($mailbox)])->save();

        $this->deliver($mailbox, $delivery->email, $contact->name, $subject, $html, $delivery->message_id, [
            'List-Unsubscribe' => '<'.$unsubscribe.'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);

        return [$subject, $html];
    }

    /**
     * Odpoveď vo vlákne ide ako správa konverzácie k veci – zákazník ju vidí
     * v tom istom vlákne a jeho odpoveď príde do inboxu ku správnej veci.
     *
     * @return array{0: string, 1: string}
     */
    private function sendReply(MailingCampaign $campaign, MailingDelivery $delivery): array
    {
        $variables = $delivery->variables();
        $quoted = $campaign->quote_event ? $this->quotedMessage($campaign, $delivery) : null;
        $html = $this->renderer->renderReply($campaign, $variables, $quoted);
        $subject = $quoted ? self::replySubject((string) $quoted->subject) : MailingRenderer::fillText((string) $campaign->subject, $variables);

        if ($this->dryRun()) {
            $this->logDryRun($delivery->email, $subject, $campaign, $delivery);

            return [$subject, $html];
        }

        $message = $this->conversations->sendFromProject($campaign->product, [
            'to' => $delivery->email,
            'to_name' => $delivery->payload['name'] ?? null,
            'subject' => $subject,
            'html' => $html,
            'ref' => [
                'type' => $delivery->ref_type,
                'id' => $delivery->ref_id,
                'label' => $delivery->payload['ref_label'] ?? null,
                'url' => $delivery->payload['ref_url'] ?? null,
            ],
            'kind' => 'mailing.reply',
            'mailbox' => $campaign->mailbox?->from_address,
            'thread_id' => $delivery->payload['thread_id'] ?? null,
            'meta' => ['mailing_delivery' => $delivery->id, 'campaign' => $campaign->id],
        ]);

        $delivery->forceFill(['conversation_message_id' => $message->id, 'message_id' => $message->message_id])->save();

        return [$subject, $html];
    }

    /**
     * Systémový mail udalosti, na ktorý sa odpovedá – Account ho má, lebo
     * projekt ho poslal cez /api/v1/messages. `kind` určil projekt pri
     * registrácii udalosti.
     */
    public function quotedMessage(MailingCampaign $campaign, ?MailingDelivery $delivery = null): ?ConversationMessage
    {
        $kind = MailingTrigger::query()->where('product_id', $campaign->product_id)
            ->where('key', $campaign->trigger_event)->value('quote_kind');

        if (! $kind) {
            return null;
        }

        return ConversationMessage::query()
            ->where('direction', ConversationMessage::OUT)
            ->where('kind', $kind)
            ->whereHas('conversation', fn ($q) => $q->where('product_id', $campaign->product_id)
                ->when($delivery, fn ($q) => $q->where('ref_type', $delivery->ref_type)
                    ->where('ref_id', $delivery->ref_id)
                    ->where('contact_email', $delivery->email)))
            ->latest('id')
            ->first();
    }

    /**
     * Test ide len prihlásenému operátorovi, priamo zo schránky – nezakladá
     * konverzáciu ani doručenie.
     *
     * @param  array<string, mixed>  $variables
     */
    public function sendTest(MailingCampaign $campaign, string $email, array $variables = []): void
    {
        $mailbox = $this->mailbox($campaign);
        $html = $this->preview($campaign, $variables);
        $quoted = $campaign->isReply() && $campaign->quote_event ? $this->quotedMessage($campaign) : null;
        $subject = '[TEST] '.($quoted ? self::replySubject((string) $quoted->subject) : MailingRenderer::fillText((string) $campaign->subject, $variables));

        if ($this->dryRun()) {
            $this->logDryRun($email, $subject, $campaign);

            return;
        }

        $this->deliver($mailbox, $email, null, $subject, $html, MessageIds::generate($mailbox));
    }

    /** @param  array<string, mixed>  $variables */
    public function preview(MailingCampaign $campaign, array $variables = []): string
    {
        if ($campaign->isReply()) {
            return $this->renderer->renderReply($campaign, $variables, $campaign->quote_event ? $this->quotedMessage($campaign) : null, true);
        }

        return $this->renderer->render($campaign, null, $variables, null);
    }

    /**
     * Nedoručiteľná adresa (z odpovede SMTP, správy o nedoručení alebo ručne).
     * Trvalý bounce blokuje všetky maily projektu, dočasný vylúči kontakt
     * z ponúk až po opakovanom zlyhaní za sebou.
     *
     * @return bool či je adresa po zápise vylúčená
     */
    public function recordBounce(Product $product, string $email, string $type, string $reason = ''): bool
    {
        $email = strtolower(trim($email));
        $reason = mb_substr($reason, 0, 255);

        return DB::transaction(function () use ($product, $email, $type, $reason) {
            $contact = MailingContact::query()->where('product_id', $product->id)->where('email', $email)->lockForUpdate()->first();

            if ($type === BounceClassifier::HARD) {
                $suppression = MailSuppression::firstOrNew(['product_id' => $product->id, 'email' => $email]);
                $suppression->reason = MailSuppression::HARD_BOUNCE;
                $suppression->bounce_count = $suppression->bounce_count + 1;
                $suppression->note = $reason;
                $suppression->save();
                $contact?->forceFill(['bounce_reason' => $reason])->save();
                $suppressed = true;
            } elseif ($contact && ! $contact->bounced_at) {
                $soft = $contact->soft_bounces + 1;
                $suppressed = $soft >= MailingContact::SOFT_BOUNCE_LIMIT;
                $contact->forceFill([
                    'soft_bounces' => $soft,
                    'bounce_reason' => $reason,
                    'bounced_at' => $suppressed ? now() : null,
                ])->save();
            } else {
                return (bool) $contact?->bounced_at;
            }

            if ($suppressed) {
                // Čakajúce ponuky tejto adresy sa už nemajú odosielať.
                MailingDelivery::query()
                    ->where('email', $email)
                    ->where('status', MailingDelivery::PENDING)
                    ->whereHas('campaign', fn ($q) => $q->where('product_id', $product->id))
                    ->update(['status' => MailingDelivery::SKIPPED, 'error' => __('mailing.errors.suppressed'), 'updated_at' => now()]);
            }

            return $suppressed;
        });
    }

    /** Hromadná kampaň bez čakajúcich doručení je hotová. */
    private function complete(MailingCampaign $campaign): void
    {
        if ($campaign->isAutomation()) {
            return;
        }

        $open = $campaign->deliveries()->whereIn('status', [MailingDelivery::PENDING, MailingDelivery::SENDING])->exists();

        if (! $open) {
            MailingCampaign::query()->whereKey($campaign->id)->where('status', MailingCampaign::QUEUED)
                ->update(['status' => MailingCampaign::COMPLETED, 'updated_at' => now()]);
        }
    }

    private function mailbox(MailingCampaign $campaign): Mailbox
    {
        return $campaign->mailbox
            ?? Mailbox::defaultFor($campaign->product)
            ?? throw MessagingException::noMailbox();
    }

    /** @param  array<string, string>  $headers */
    private function deliver(Mailbox $mailbox, string $to, ?string $name, string $subject, string $html, string $messageId, array $headers = []): void
    {
        if ($this->dryRun()) {
            $this->logDryRun($to, $subject);

            return;
        }

        $this->outbound->mailer($mailbox)->send(['html' => new HtmlString($html)], [], function (Message $mail) use ($mailbox, $to, $name, $subject, $messageId, $headers) {
            $mail->from($mailbox->from_address, $mailbox->from_name);
            $mail->replyTo($mailbox->from_address, $mailbox->from_name);
            $mail->to($to, $name);
            $mail->subject($subject);

            $symfony = $mail->getSymfonyMessage()->getHeaders();
            $symfony->addIdHeader('Message-ID', $messageId);

            foreach ($headers as $header => $value) {
                $symfony->addTextHeader($header, $value);
            }
        });
    }

    private function logDryRun(string $to, string $subject, ?MailingCampaign $campaign = null, ?MailingDelivery $delivery = null): void
    {
        // Všetko vrátane vykreslenia a merania prebehlo; len sa nevolá poštový server.
        Log::info('Mailing dry-run: e-mail sa neodoslal.', [
            'to' => $to, 'subject' => $subject, 'campaign_id' => $campaign?->id, 'delivery_id' => $delivery?->id,
        ]);
    }

    public static function replySubject(string $subject): string
    {
        return preg_match('/^(re|aw|odp|odpoveď)\s*:/iu', $subject) ? $subject : 'Re: '.$subject;
    }
}
