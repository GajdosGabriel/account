<?php

namespace App\Services\Webhooks;

use App\Jobs\DeliverWebhook;
use App\Models\Product;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Str;

/**
 * Rozposiela udalosti pripojeným projektom.
 *
 * Každá udalosť sa najprv zapíše ako `webhook_delivery` (aby sme mali
 * doručovaciu históriu a mohli opakovať), až potom sa zaradí do fronty.
 */
class WebhookDispatcher
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, WebhookDelivery>
     */
    public function dispatch(string $event, array $payload, ?Product $product = null): array
    {
        $eventId = (string) Str::uuid();

        $endpoints = WebhookEndpoint::query()
            ->where('is_active', true)
            ->when($product, fn ($q) => $q->where('product_id', $product->id))
            ->get()
            ->filter(fn (WebhookEndpoint $endpoint) => $endpoint->listensTo($event));

        $deliveries = [];

        foreach ($endpoints as $endpoint) {
            $delivery = WebhookDelivery::create([
                'webhook_endpoint_id' => $endpoint->id,
                'event_id' => $eventId,
                'event' => $event,
                'payload' => [
                    'id' => $eventId,
                    'event' => $event,
                    'created_at' => now()->toIso8601String(),
                    'data' => $payload,
                ],
                'next_attempt_at' => now(),
            ]);

            // afterCommit, lebo `organization.created` vzniká vnútri transakcie,
            // v ktorej sa firma zakladá. Bez neho by worker mohol udalosť
            // doručiť skôr, než transakcia skončí – projekt by si prišiel po
            // firmu, ktorá pre neho ešte neexistuje. Mimo transakcie sa job
            // zaradí okamžite, takže to nič nespomaľuje.
            DeliverWebhook::dispatch($delivery->id)->afterCommit();

            $deliveries[] = $delivery;
        }

        return $deliveries;
    }

    /**
     * Podpis v hlavičke: HMAC-SHA256 z "timestamp.telo".
     * Príjemca musí overiť aj časovú pečiatku (ochrana proti replay).
     */
    public static function signature(string $secret, int $timestamp, string $body): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }
}
