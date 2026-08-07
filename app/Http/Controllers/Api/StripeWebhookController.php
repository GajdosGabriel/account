<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\Billing\SubscriptionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Prijíma udalosti zo Stripe a prekladá ich na prechody
 * v našom stavovom automate. Podpis overujeme ručne, takže
 * nepotrebujeme stripe-php SDK.
 */
class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, SubscriptionManager $manager): JsonResponse
    {
        if (! $this->signatureIsValid($request)) {
            return response()->json(['message' => 'Neplatný podpis.'], 400);
        }

        $type = $request->input('type');
        $object = $request->input('data.object', []);

        $subscription = $this->resolveSubscription($object);

        if (! $subscription) {
            return response()->json(['message' => 'Predplatné sa nenašlo, ignorujem.'], 202);
        }

        match ($type) {
            'invoice.payment_succeeded',
            'customer.subscription.resumed' => $manager->activate($subscription, $type),

            'invoice.payment_failed' => $manager->markPastDue($subscription, $type),

            'customer.subscription.deleted' => $manager->cancel($subscription, $type),

            default => Log::info('Nespracovaná Stripe udalosť', ['type' => $type]),
        };

        return response()->json(['received' => true]);
    }

    /** @param array<string, mixed> $object */
    protected function resolveSubscription(array $object): ?Subscription
    {
        $subscriptionId = $object['subscription'] ?? $object['id'] ?? null;
        $customerId = $object['customer'] ?? null;

        return Subscription::query()
            ->when($subscriptionId, fn ($q) => $q->orWhere('external_id', $subscriptionId))
            ->when($customerId, fn ($q) => $q->orWhere('external_customer_id', $customerId))
            ->first();
    }

    protected function signatureIsValid(Request $request): bool
    {
        $secret = config('services.stripe.webhook_secret');

        // V lokálnom vývoji bez kľúča webhook prepúšťame.
        if (blank($secret)) {
            return ! app()->isProduction();
        }

        $header = $request->header('Stripe-Signature', '');

        preg_match('/t=(\d+)/', $header, $timestampMatch);
        preg_match('/v1=([a-f0-9]+)/', $header, $signatureMatch);

        $timestamp = $timestampMatch[1] ?? null;
        $signature = $signatureMatch[1] ?? null;

        if (! $timestamp || ! $signature) {
            return false;
        }

        // Ochrana proti replay útoku – tolerancia 5 minút.
        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }
}
