<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\Billing\SubscriptionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Prijíma udalosti zo Stripe a prekladá ich na prechody
 * v našom stavovom automate. Podpis overujeme ručne, takže
 * nepotrebujeme stripe-php SDK.
 */
class StripeWebhookController extends Controller
{
    /** Ako dlho si pamätáme spracované udalosti. Stripe opakuje doručenie až 3 dni. */
    private const SEEN_TTL_DAYS = 4;

    public function __invoke(Request $request, SubscriptionManager $manager): JsonResponse
    {
        if (! $this->signatureIsValid($request)) {
            return response()->json(['message' => 'Neplatný podpis.'], 400);
        }

        $type = $request->input('type');
        $object = $request->input('data.object', []);

        $subscription = $this->resolveSubscription(is_array($object) ? $object : []);

        if (! $subscription) {
            return response()->json(['message' => 'Predplatné sa nenašlo, ignorujem.'], 202);
        }

        // Stripe tú istú udalosť pokojne doručí viackrát (timeout, opakovanie).
        // Prechod stavu sa však nesmie zopakovať – napr. druhé „activate“
        // po medzitým prijatom „payment_failed“ by vrátilo prístup.
        $eventId = $request->input('id');

        if (is_string($eventId) && $eventId !== ''
            && ! Cache::add('stripe:event:'.$eventId, true, now()->addDays(self::SEEN_TTL_DAYS))) {
            return response()->json(['received' => true, 'duplicate' => true]);
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

    /**
     * Predplatné hľadáme podľa jeho Stripe ID. Zákazník (cus_…) môže mať
     * predplatných viac, preto podľa neho párujeme len vtedy, keď je
     * jednoznačný – inak by sa zrušenie jedného projektu prenieslo na iný.
     *
     * @param  array<string, mixed>  $object
     */
    protected function resolveSubscription(array $object): ?Subscription
    {
        $subscriptionId = $object['subscription'] ?? null;

        // Pri udalostiach customer.subscription.* je predplatným samotný objekt.
        if (! $subscriptionId && ($object['object'] ?? null) === 'subscription') {
            $subscriptionId = $object['id'] ?? null;
        }

        if (is_string($subscriptionId) && $subscriptionId !== '') {
            return Subscription::query()->where('external_id', $subscriptionId)->first();
        }

        $customerId = $object['customer'] ?? null;

        if (! is_string($customerId) || $customerId === '') {
            return null;
        }

        $matches = Subscription::query()->where('external_customer_id', $customerId)->limit(2)->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    protected function signatureIsValid(Request $request): bool
    {
        $secret = config('services.stripe.webhook_secret');

        // Bez kľúča prepúšťame len lokálny vývoj a testy. Staging či produkcia
        // so zabudnutým kľúčom musia webhook odmietnuť, nie mu veriť.
        if (blank($secret)) {
            return app()->environment('local', 'testing');
        }

        $header = (string) $request->header('Stripe-Signature', '');

        preg_match('/t=(\d+)/', $header, $timestampMatch);
        // Pri rotácii kľúča posiela Stripe viac podpisov v1 – stačí jeden platný.
        preg_match_all('/v1=([a-f0-9]+)/', $header, $signatureMatches);

        $timestamp = $timestampMatch[1] ?? null;
        $signatures = $signatureMatches[1] ?? [];

        if (! $timestamp || $signatures === []) {
            return false;
        }

        // Ochrana proti replay útoku – tolerancia 5 minút.
        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }
}
