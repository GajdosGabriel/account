<?php

namespace App\Listeners;

use App\Events\SubscriptionStatusChanged;
use App\Services\Entitlements\EntitlementService;
use App\Services\Webhooks\WebhookDispatcher;

/**
 * Po každej zmene stavu predplatného pošleme projektu webhook
 * s novým entitlementom, aby si mohol okamžite invalidovať cache.
 */
class BroadcastSubscriptionChange
{
    public function __construct(
        private readonly WebhookDispatcher $dispatcher,
        private readonly EntitlementService $entitlements,
    ) {}

    public function handle(SubscriptionStatusChanged $event): void
    {
        $subscription = $event->subscription;
        $organization = $subscription->organization;
        $product = $subscription->product;

        $this->dispatcher->dispatch('subscription.status_changed', [
            'organization_id' => $organization->uuid,
            'product' => $product->key,
            'from' => $event->from?->value,
            'to' => $event->to->value,
            'reason' => $event->reason,
            'entitlements' => $this->entitlements->for($organization, $product, fresh: true),
        ], $product);
    }
}
