<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ServiceClient;
use App\Models\WebhookEndpoint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Správa pripojených projektov: service tokeny, webhook endpointy
 * a OAuth klienti. Prístupné iba super-adminovi (prevádzkovateľovi).
 */
class DeveloperController extends Controller
{
    public function index(): Response
    {
        $products = Product::query()
            ->with(['serviceClients', 'webhookEndpoints'])
            ->get()
            ->map(fn (Product $product) => [
                'key' => $product->key,
                'name' => $product->name,
                'url' => $product->url,
                'tokens' => $product->serviceClients->map(fn (ServiceClient $client) => [
                    'id' => $client->id,
                    'name' => $client->name,
                    'prefix' => $client->token_prefix,
                    'abilities' => $client->abilities ?? [],
                    'last_used_at' => $client->last_used_at?->diffForHumans(),
                    'revoked' => $client->isRevoked(),
                ]),
                'webhooks' => $product->webhookEndpoints->map(fn (WebhookEndpoint $endpoint) => [
                    'id' => $endpoint->id,
                    'url' => $endpoint->url,
                    'events' => $endpoint->events ?? ['*'],
                    'is_active' => $endpoint->is_active,
                    'secret_preview' => substr($endpoint->secret, 0, 12).'…',
                ]),
            ]);

        return Inertia::render('Developers/Index', [
            'products' => $products,
            'available_events' => [
                'organization.updated',
                'organization.deleted',
                'subscription.status_changed',
                'entitlements.changed',
            ],
        ]);
    }

    public function storeToken(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_key' => ['required', 'exists:products,key'],
            'name' => ['required', 'string', 'max:120'],
        ]);

        $product = Product::where('key', $data['product_key'])->firstOrFail();

        [, $plain] = ServiceClient::issue($product, $data['name']);

        // Token sa zobrazí iba raz – posielame ho cez flash session.
        return back()->with('success', "Nový token (ulož si ho, už sa nezobrazí): {$plain}");
    }

    public function revokeToken(ServiceClient $client): RedirectResponse
    {
        $client->forceFill(['revoked_at' => now()])->save();

        return back()->with('success', 'Token bol zrušený.');
    }

    public function storeWebhook(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_key' => ['required', 'exists:products,key'],
            'url' => ['required', 'url', 'max:255'],
            'events' => ['array'],
            'events.*' => ['string'],
        ]);

        $product = Product::where('key', $data['product_key'])->firstOrFail();

        $endpoint = $product->webhookEndpoints()->create([
            'url' => $data['url'],
            'events' => $data['events'] ?? null,
        ]);

        return back()->with('success', "Webhook pridaný. Podpisový kľúč: {$endpoint->secret}");
    }

    public function destroyWebhook(WebhookEndpoint $endpoint): RedirectResponse
    {
        $endpoint->delete();

        return back()->with('success', 'Webhook bol odstránený.');
    }
}
