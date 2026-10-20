<?php

namespace App\Services\Mailing;

use App\Models\MailingContact;
use App\Models\MailingDelivery;
use App\Services\Webhooks\WebhookDispatcher;

/**
 * Udalosti emailingu smerom do projektu.
 *
 *   mailing.unsubscribed      kontakt sa odhlásil z ponúk
 *   mailing.delivery.sent     e-mail automatizácie k veci odišiel (napr. do histórie objednávky)
 *   mailing.delivery.failed   nepodarilo sa ho odoslať
 *   mailing.delivery.bounced  adresa je nedoručiteľná
 *
 * Doručenia hromadnej ponuky (bez `ref`) sa neposielajú – projekt s nimi
 * nič nerobí a pri tisícoch adresátov by to boli tisíce webhookov.
 */
class MailingWebhooks
{
    public const EVENTS = ['mailing.unsubscribed', 'mailing.delivery.sent', 'mailing.delivery.failed', 'mailing.delivery.bounced'];

    public function __construct(private WebhookDispatcher $dispatcher) {}

    public function unsubscribed(MailingContact $contact): void
    {
        $this->dispatcher->dispatch('mailing.unsubscribed', [
            'contact' => ['email' => $contact->email, 'name' => $contact->name],
            'unsubscribed_at' => $contact->unsubscribed_at?->toIso8601String(),
        ], $contact->product);
    }

    public function delivery(string $event, MailingDelivery $delivery, ?string $subject = null, ?string $html = null): void
    {
        if (! $delivery->ref_type) {
            return;
        }

        $campaign = $delivery->campaign;

        $this->dispatcher->dispatch($event, [
            'delivery' => [
                'id' => $delivery->id,
                'status' => $delivery->status,
                'email' => $delivery->email,
                'ref' => ['type' => $delivery->ref_type, 'id' => $delivery->ref_id],
                'event' => $campaign->trigger_event,
                'dedupe_key' => $delivery->dedupe_key,
                'error' => $delivery->error,
                'sent_at' => $delivery->sent_at?->toIso8601String(),
            ],
            'campaign' => ['id' => $campaign->id, 'name' => $campaign->name, 'reply_in_thread' => $campaign->reply_in_thread],
            'subject' => $subject,
            'html' => $html,
        ], $campaign->product);
    }
}
