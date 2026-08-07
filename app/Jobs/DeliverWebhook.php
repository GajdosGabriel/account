<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Services\Webhooks\WebhookDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

class DeliverWebhook implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $deliveryId) {}

    public function handle(): void
    {
        $delivery = WebhookDelivery::with('endpoint')->find($this->deliveryId);

        if (! $delivery || $delivery->isDelivered() || ! $delivery->endpoint?->is_active) {
            return;
        }

        $endpoint = $delivery->endpoint;
        $body = json_encode($delivery->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp = now()->getTimestamp();

        $delivery->increment('attempts');

        try {
            $response = Http::timeout(config('accounts.webhooks.timeout'))
                ->withHeaders([
                    config('accounts.webhooks.signature_header') => WebhookDispatcher::signature($endpoint->secret, $timestamp, $body),
                    config('accounts.webhooks.timestamp_header') => (string) $timestamp,
                    'Content-Type' => 'application/json',
                ])
                ->withBody($body, 'application/json')
                ->post($endpoint->url);

            $delivery->forceFill([
                'response_status' => $response->status(),
                'response_body' => mb_substr($response->body(), 0, 2000),
            ]);

            if ($response->successful()) {
                $delivery->forceFill(['delivered_at' => now(), 'next_attempt_at' => null])->save();

                return;
            }
        } catch (\Throwable $e) {
            $delivery->forceFill(['response_status' => null, 'response_body' => mb_substr($e->getMessage(), 0, 2000)]);
        }

        $this->scheduleRetry($delivery);
    }

    protected function scheduleRetry(WebhookDelivery $delivery): void
    {
        $max = (int) config('accounts.webhooks.max_attempts');

        if ($delivery->attempts >= $max) {
            $delivery->forceFill(['failed_at' => now(), 'next_attempt_at' => null])->save();

            return;
        }

        $backoff = config('accounts.webhooks.backoff');
        $delay = $backoff[min($delivery->attempts - 1, count($backoff) - 1)] ?? 3600;

        $delivery->forceFill(['next_attempt_at' => now()->addSeconds($delay)])->save();
    }
}
