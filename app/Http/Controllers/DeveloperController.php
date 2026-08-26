<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ServiceClient;
use App\Models\WebhookEndpoint;
use App\Support\Abilities;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Správa pripojených projektov: service tokeny, webhook endpointy
 * a OAuth klienti. Prístupné iba super-adminovi (prevádzkovateľovi).
 *
 * Tokeny aj webhooky sa mažú mäkko – zmazaný záznam zostane v zozname
 * ako položka v koši, kým ho niekto nevráti alebo neodstráni natrvalo.
 */
class DeveloperController extends Controller
{
    public function index(): Response
    {
        $products = Product::query()
            // Zmazané sa nefiltrujú preč: bez nich by sa z koša nedali
            // dostať späť a v UI by po zmazaní ticho zmizli.
            ->with([
                'serviceClients' => fn ($q) => $q->withTrashed(),
                'webhookEndpoints' => fn ($q) => $q->withTrashed(),
            ])
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
                    ...Abilities::payload($client, [...Abilities::STANDARD, 'revoke', 'unrevoke']),
                ]),
                'webhooks' => $product->webhookEndpoints->map(fn (WebhookEndpoint $endpoint) => [
                    'id' => $endpoint->id,
                    'url' => $endpoint->url,
                    'events' => $endpoint->events ?? ['*'],
                    'is_active' => $endpoint->is_active,
                    'secret_preview' => substr($endpoint->secret, 0, 12).'…',
                    ...Abilities::payload($endpoint),
                ]),
            ]);

        return Inertia::render('Developers/Index', [
            'products' => $products,
            'available_events' => [
                'organization.created',
                'organization.updated',
                'organization.deleted',
                'subscription.status_changed',
                'entitlements.changed',
            ],
        ]);
    }

    /* ---------------------------------------------------------------
     | Service tokeny
     |---------------------------------------------------------------*/

    public function storeToken(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_key' => ['required', 'exists:products,key'],
            'name' => ['required', 'string', 'max:120'],
        ]);

        $product = Product::where('key', $data['product_key'])->firstOrFail();

        [, $plain] = ServiceClient::issue($product, $data['name']);

        // Token sa zobrazí iba raz. Do textu hlásenia nepatrí: toast
        // zmizne skôr, než ho stihneš označiť, a vyznačiť sa z neho dá
        // len myšou. Ide preto do okna s tlačidlom na skopírovanie.
        return back()->with('secret', [
            'title' => __('tokens.issued'),
            'hint' => __('tokens.issued_hint'),
            'value' => $plain,
        ]);
    }

    /**
     * Úprava tokenu má vlastnú stránku.
     *
     * Oprávnenia sú zoznam na zaškrtnutie, nie voľný text – ability,
     * ktorú `routes/api.php` nekontroluje, by v databáze vyzerala
     * platne a projekt by na ňu narazil až v produkcii.
     */
    public function editToken(ServiceClient $client): Response
    {
        $this->authorize('update', $client);

        $client->load('product');

        return Inertia::render('Developers/TokenForm', [
            'token' => [
                'id' => $client->id,
                'name' => $client->name,
                'prefix' => $client->token_prefix,
                'abilities' => $client->abilities ?? [],
                'product' => $client->product?->name,
                'product_key' => $client->product?->key,
                'last_used_at' => $client->last_used_at?->diffForHumans(),
                'created_at' => $client->created_at?->toDateTimeString(),
                'revoked' => $client->isRevoked(),
                ...Abilities::payload($client, [...Abilities::STANDARD, 'revoke', 'unrevoke']),
            ],
            'available_abilities' => $this->abilityOptions(),
        ]);
    }

    /** Popis a oprávnenia. Samotný token sa meniť nedá, ten je len hash. */
    public function updateToken(Request $request, ServiceClient $client): RedirectResponse
    {
        $this->authorize('update', $client);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => [Rule::in(ServiceClient::ABILITIES)],
        ], [
            'abilities.required' => __('tokens.abilities_required'),
            'abilities.min' => __('tokens.abilities_required'),
        ]);

        $client->update([
            'name' => $data['name'],
            // poradie podľa katalógu, nie podľa toho, čo prišlo z formulára
            'abilities' => array_values(array_intersect(ServiceClient::ABILITIES, $data['abilities'])),
        ]);

        return redirect()->route('developers.index')->with('success', __('tokens.saved'));
    }

    /**
     * Oprávnenia do formulára aj s vysvetlením, čo ktoré otvára.
     *
     * @return array<int, array<string, string>>
     */
    protected function abilityOptions(): array
    {
        return array_map(fn (string $ability) => [
            'value' => $ability,
            'label' => __("tokens.ability.{$ability}.label"),
            'description' => __("tokens.ability.{$ability}.description"),
        ], ServiceClient::ABILITIES);
    }

    /**
     * Zrušenie nie je mazanie: záznam zostáva kvôli auditu, len ním
     * už neprejde autentifikácia.
     */
    public function revokeToken(ServiceClient $client): RedirectResponse
    {
        $this->authorize('revoke', $client);

        $client->forceFill(['revoked_at' => now()])->save();

        return back()->with('success', __('tokens.revoked'));
    }

    /**
     * Návrat zrušeného tokenu.
     *
     * Hash v databáze sa pri zrušení nemenil, takže projekt pokračuje
     * s tou istou hodnotou – nič si nemusí prehadzovať v konfigurácii.
     */
    public function unrevokeToken(ServiceClient $client): RedirectResponse
    {
        $this->authorize('unrevoke', $client);

        $client->forceFill(['revoked_at' => null])->save();

        return back()->with('success', __('tokens.unrevoked'));
    }

    public function destroyToken(ServiceClient $client): RedirectResponse
    {
        $this->authorize('delete', $client);

        $client->delete();

        return back()->with('success', __('actions.flash.deleted'));
    }

    public function restoreToken(ServiceClient $client): RedirectResponse
    {
        $this->authorize('restore', $client);

        $client->restore();

        return back()->with('success', __('actions.flash.restored'));
    }

    public function forceDeleteToken(ServiceClient $client): RedirectResponse
    {
        $this->authorize('forceDelete', $client);

        $client->forceDelete();

        return back()->with('success', __('actions.flash.force_deleted'));
    }

    /* ---------------------------------------------------------------
     | Webhooky
     |---------------------------------------------------------------*/

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

        return back()->with('secret', [
            'title' => __('tokens.webhook_secret'),
            'hint' => __('tokens.webhook_secret_hint'),
            'value' => $endpoint->secret,
        ]);
    }

    public function updateWebhook(Request $request, WebhookEndpoint $endpoint): RedirectResponse
    {
        $this->authorize('update', $endpoint);

        $data = $request->validate([
            'url' => ['required', 'url', 'max:255'],
            'events' => ['array'],
            'events.*' => ['string'],
            'is_active' => ['boolean'],
        ]);

        $endpoint->update([
            'url' => $data['url'],
            'events' => $data['events'] ?? null,
            'is_active' => $data['is_active'] ?? $endpoint->is_active,
        ]);

        return back()->with('success', 'Webhook bol upravený.');
    }

    public function destroyWebhook(WebhookEndpoint $endpoint): RedirectResponse
    {
        $this->authorize('delete', $endpoint);

        $endpoint->delete();

        return back()->with('success', __('actions.flash.deleted'));
    }

    public function restoreWebhook(WebhookEndpoint $endpoint): RedirectResponse
    {
        $this->authorize('restore', $endpoint);

        $endpoint->restore();

        return back()->with('success', __('actions.flash.restored'));
    }

    public function forceDeleteWebhook(WebhookEndpoint $endpoint): RedirectResponse
    {
        $this->authorize('forceDelete', $endpoint);

        $endpoint->forceDelete();

        return back()->with('success', __('actions.flash.force_deleted'));
    }
}
