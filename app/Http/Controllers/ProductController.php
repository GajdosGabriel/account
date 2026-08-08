<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductFeature;
use App\Services\Entitlements\EntitlementService;
use App\Support\Abilities;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Správa projektov: katalóg funkcií a cenníky.
 *
 * Katalóg je dôležitejší, než sa zdá – bez neho je `plans.features`
 * voľný JSON, kde preklep v kľúči znamená, že projekt limit nenájde
 * a pustí neobmedzene.
 */
class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $products = Product::withTrashed()
            ->with(['features', 'plans'])
            ->withCount('organizations')
            ->when($search, fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$term}%")
                ->orWhere('key', 'like', "%{$term}%")))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Product $product) => [
                'key' => $product->key,
                'name' => $product->name,
                'url' => $product->url,
                'description' => $product->description,
                'is_active' => $product->is_active,
                'organizations_count' => $product->organizations_count,
                'features_count' => $product->features->count(),
                'plans_count' => $product->plans->count(),
                ...Abilities::payload($product),
            ]);

        return Inertia::render('Products/Index', [
            'products' => $products,
            'filters' => ['search' => $search],
        ]);
    }

    public function show(Product $product): Response
    {
        // Aj katalóg a cenník ukazujú zmazané položky – inak by sa z koša
        // nedali vrátiť a po zmazaní by z obrazovky ticho zmizli.
        $product->load([
            'features' => fn ($q) => $q->withTrashed(),
            'plans' => fn ($q) => $q->withTrashed(),
        ]);

        return Inertia::render('Products/Show', [
            'product' => [
                'key' => $product->key,
                'name' => $product->name,
                'url' => $product->url,
                'description' => $product->description,
                'is_active' => $product->is_active,
                ...Abilities::payload($product),
            ],
            'features' => $product->features->map(fn (ProductFeature $feature) => [
                'id' => $feature->id,
                'key' => $feature->key,
                'name' => $feature->name,
                'type' => $feature->type,
                'unit' => $feature->unit,
                'metric' => $feature->metric,
                'default_value' => $feature->defaultValue(),
                'description' => $feature->description,
                'sort_order' => $feature->sort_order,
                ...Abilities::payload($feature),
            ]),
            'plans' => $product->plans->map(fn (Plan $plan) => [
                'id' => $plan->id,
                'key' => $plan->key,
                'name' => $plan->name,
                'price_cents' => $plan->price_cents,
                'price' => $plan->formattedPrice(),
                'interval' => $plan->interval,
                'trial_days' => $plan->trial_days,
                'is_active' => $plan->is_active,
                'sort_order' => $plan->sort_order,
                // hodnoty su vzdy podla katalogu, aj ked plan kluc neuvadza
                'features' => $product->features->mapWithKeys(fn (ProductFeature $feature) => [
                    $feature->key => $plan->features[$feature->key] ?? $feature->defaultValue(),
                ]),
                ...Abilities::payload($plan),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9-]+$/', 'unique:products,key'],
            'name' => ['required', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $product = Product::create($data);

        AuditLog::record('product.created', $product, [], null, $product->id);

        return redirect()->route('products.show', $product)->with('success', 'Projekt bol vytvorený.');
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'key' => [
                'required', 'string', 'max:50', 'regex:/^[a-z0-9-]+$/',
                Rule::unique('products', 'key')->ignore($product->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ]);

        $keyChanged = $product->key !== $data['key'];

        $product->update($data);

        AuditLog::record('product.updated', $product, ['changed' => array_keys($product->getChanges())], null, $product->id);

        // Kľúč je zároveň adresa v URL – po zmene treba presmerovať inam,
        // inak by back() skončilo na neexistujúcej stránke.
        if ($keyChanged) {
            return redirect()->route('products.show', $product)
                ->with('success', "Kľúč projektu zmenený na „{$product->key}\u{201c}. Uprav ho aj v konfigurácii projektu.");
        }

        return back()->with('success', 'Projekt bol upravený.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        // Zmazanie by vzalo aj plány, katalóg, tokeny a webhooky.
        if ($product->organizations()->exists()) {
            return back()->with('error', 'Projekt používajú organizácie – najprv ich odviaž.');
        }

        $this->authorize('delete', $product);

        $product->delete();

        AuditLog::record('product.deleted', null, ['name' => $product->name]);

        return redirect()->route('products.index')->with('success', __('actions.flash.deleted'));
    }

    public function restore(Product $product): RedirectResponse
    {
        $this->authorize('restore', $product);

        $product->restore();

        AuditLog::record('product.restored', $product, [], null, $product->id);

        return back()->with('success', __('actions.flash.restored'));
    }

    public function forceDelete(Product $product): RedirectResponse
    {
        $this->authorize('forceDelete', $product);

        $name = $product->name;
        $product->forceDelete();

        AuditLog::record('product.force_deleted', null, ['name' => $name]);

        return redirect()->route('products.index')->with('success', __('actions.flash.force_deleted'));
    }

    /* ---------------------------------------------------------------
     | Katalóg funkcií
     |---------------------------------------------------------------*/

    public function storeFeature(Request $request, Product $product): RedirectResponse
    {
        $data = $this->validateFeature($request, $product);

        $product->features()->create($this->featureAttributes($data));

        $this->flushProduct($product);

        return back()->with('success', 'Funkcia bola pridaná do katalógu.');
    }

    public function updateFeature(Request $request, Product $product, ProductFeature $feature): RedirectResponse
    {
        $this->authorize('update', $feature);

        $data = $this->validateFeature($request, $product, $feature);

        $feature->update($this->featureAttributes($data));

        $this->flushProduct($product);

        return back()->with('success', 'Funkcia bola upravená.');
    }

    public function destroyFeature(Product $product, ProductFeature $feature): RedirectResponse
    {
        $this->authorize('delete', $feature);

        $feature->delete();

        $this->flushProduct($product);

        return back()->with('success', __('actions.flash.deleted'));
    }

    public function restoreFeature(Product $product, ProductFeature $feature): RedirectResponse
    {
        $this->authorize('restore', $feature);

        $feature->restore();

        $this->flushProduct($product);

        return back()->with('success', __('actions.flash.restored'));
    }

    public function forceDeleteFeature(Product $product, ProductFeature $feature): RedirectResponse
    {
        $this->authorize('forceDelete', $feature);

        $feature->forceDelete();

        $this->flushProduct($product);

        return back()->with('success', __('actions.flash.force_deleted'));
    }

    /* ---------------------------------------------------------------
     | Plány
     |---------------------------------------------------------------*/

    public function storePlan(Request $request, Product $product): RedirectResponse
    {
        $data = $this->validatePlan($request, $product);

        $product->plans()->create($this->planAttributes($product, $data));

        $this->flushProduct($product);

        return back()->with('success', 'Plán bol vytvorený.');
    }

    public function updatePlan(Request $request, Product $product, Plan $plan): RedirectResponse
    {
        $this->authorize('update', $plan);

        $data = $this->validatePlan($request, $product, $plan);

        $plan->update($this->planAttributes($product, $data));

        $this->flushProduct($product);

        return back()->with('success', 'Plán bol upravený.');
    }

    public function destroyPlan(Product $product, Plan $plan): RedirectResponse
    {
        if ($plan->subscriptions()->exists()) {
            return back()->with('error', 'Plán sa používa, najprv presuň zákazníkov inam.');
        }

        $this->authorize('delete', $plan);

        $plan->delete();

        return back()->with('success', __('actions.flash.deleted'));
    }

    public function restorePlan(Product $product, Plan $plan): RedirectResponse
    {
        $this->authorize('restore', $plan);

        $plan->restore();

        return back()->with('success', __('actions.flash.restored'));
    }

    public function forceDeletePlan(Product $product, Plan $plan): RedirectResponse
    {
        $this->authorize('forceDelete', $plan);

        $plan->forceDelete();

        return back()->with('success', __('actions.flash.force_deleted'));
    }

    /* ---------------------------------------------------------------
     | Pomocné
     |---------------------------------------------------------------*/

    /**
     * @return array<string, mixed>
     */
    protected function validateFeature(Request $request, Product $product, ?ProductFeature $feature = null): array
    {
        return $request->validate([
            'key' => [
                'required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/',
                Rule::unique('product_features', 'key')
                    ->where('product_id', $product->id)
                    ->ignore($feature?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:flag,limit'],
            'unit' => ['nullable', 'string', 'max:30'],
            'metric' => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/'],
            'default_value' => ['nullable'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function featureAttributes(array $data): array
    {
        $isFlag = $data['type'] === ProductFeature::TYPE_FLAG;

        $default = $isFlag
            ? filter_var($data['default_value'] ?? false, FILTER_VALIDATE_BOOL)
            : (($data['default_value'] ?? null) === null || $data['default_value'] === '' ? null : (int) $data['default_value']);

        return [
            'key' => $data['key'],
            'name' => $data['name'],
            'type' => $data['type'],
            // prepínač nemá jednotku ani metriku spotreby
            'unit' => $isFlag ? null : ($data['unit'] ?? null),
            'metric' => $isFlag ? null : ($data['metric'] ?? null),
            'default_value' => ['value' => $default],
            'description' => $data['description'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatePlan(Request $request, Product $product, ?Plan $plan = null): array
    {
        return $request->validate([
            'key' => [
                'required', 'string', 'max:50', 'regex:/^[a-z0-9-]+$/',
                Rule::unique('plans', 'key')->where('product_id', $product->id)->ignore($plan?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'price_cents' => ['required', 'integer', 'min:0'],
            'interval' => ['required', 'in:month,year'],
            'trial_days' => ['required', 'integer', 'min:0', 'max:365'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            // kluce musia existovat v katalogu - inak by preklep prosiel
            'features' => ['array'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function planAttributes(Product $product, array $data): array
    {
        $features = [];

        foreach ($product->features as $feature) {
            if (array_key_exists($feature->key, $data['features'] ?? [])) {
                $features[$feature->key] = $feature->castValue($data['features'][$feature->key]);
            }
        }

        return [
            'key' => $data['key'],
            'name' => $data['name'],
            'price_cents' => $data['price_cents'],
            'interval' => $data['interval'],
            'trial_days' => $data['trial_days'],
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
            'features' => $features,
        ];
    }

    protected function flushProduct(Product $product): void
    {
        $entitlements = app(EntitlementService::class);

        foreach ($product->organizations()->get() as $organization) {
            $entitlements->flush($organization, $product);
        }
    }
}
