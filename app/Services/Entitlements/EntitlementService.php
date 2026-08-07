<?php

namespace App\Services\Entitlements;

use App\Enums\SubscriptionStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductFeature;
use App\Models\UsageReport;
use Illuminate\Support\Facades\Cache;

/**
 * Jediné miesto, ktoré odpovedá na otázku
 * „čo smie táto firma v tomto projekte a koľko toho už minula".
 *
 * Hodnota funkcie sa skladá v tomto poradí:
 *
 *   1. default z katalógu (product_features.default_value)
 *   2. hodnota z plánu    (plans.features)
 *   3. ručná výnimka      (entitlement_overrides)
 *
 * Pri limitoch platí: null = neobmedzene. Nikdy nie -1 ani 0 —
 * pri tých by porovnanie `$pocet > $limit` vyhodnotilo nesprávne.
 */
class EntitlementService
{
    public const CACHE_TTL = 300; // 5 minút

    /**
     * @return array<string, mixed>
     */
    public function for(Organization $organization, Product $product, bool $fresh = false): array
    {
        $key = $this->cacheKey($organization, $product);

        if ($fresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, self::CACHE_TTL, fn () => $this->resolve($organization, $product));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function forAllProducts(Organization $organization): array
    {
        return $organization->products()
            ->where('is_active', true)
            ->get()
            ->mapWithKeys(fn (Product $product) => [$product->key => $this->for($organization, $product)])
            ->all();
    }

    public function flush(Organization $organization, ?Product $product = null): void
    {
        $products = $product ? [$product] : Product::all()->all();

        foreach ($products as $item) {
            Cache::forget($this->cacheKey($organization, $item));
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolve(Organization $organization, Product $product): array
    {
        $catalog = $product->features()->get();
        $subscription = $organization->subscriptionFor($product);
        $status = $subscription?->status;

        $features = $this->composeFeatures($catalog, $organization, $product, $subscription?->plan?->features ?? []);

        $accessible = $organization->isActive() && $status?->grantsAccess();
        $readOnly = $organization->isActive() && ($status?->isReadOnly() ?? false);

        // Pozastavené predplatné = žiadne platené funkcie, iba čítanie.
        if ($readOnly) {
            $features = $this->downgradeToReadOnly($catalog, $features);
        }

        $usage = $this->currentUsage($organization, $product);

        return [
            'product' => $product->key,
            'organization' => $organization->uuid,
            'plan' => $subscription?->plan?->key,
            'plan_name' => $subscription?->plan?->name,
            'status' => $status?->value ?? 'none',
            'access' => (bool) $accessible,
            'read_only' => $readOnly,
            'features' => $features,
            'usage' => $usage,
            // vypíše sa iba to, čo je nad limit – projekt tak vie ukázať
            // „máte 15 z 10" namiesto tvrdého zamknutia
            'over_limit' => $this->overLimit($catalog, $features, $usage),
            'trial_ends_at' => $subscription?->trial_ends_at?->toIso8601String(),
            'current_period_end' => $subscription?->current_period_end?->toIso8601String(),
            'grace_ends_at' => $subscription?->grace_ends_at?->toIso8601String(),
            'resolved_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ProductFeature>  $catalog
     * @param  array<string, mixed>  $planFeatures
     * @return array<string, mixed>
     */
    protected function composeFeatures($catalog, Organization $organization, Product $product, array $planFeatures): array
    {
        $features = [];

        foreach ($catalog as $feature) {
            $features[$feature->key] = array_key_exists($feature->key, $planFeatures)
                ? $planFeatures[$feature->key]
                : $feature->defaultValue();
        }

        // Ručné výnimky prepíšu plán (napr. dočasne zvýšený limit).
        $overrides = $organization->entitlementOverrides()
            ->where('product_id', $product->id)
            ->get();

        foreach ($overrides as $override) {
            if ($override->isActive() && array_key_exists($override->feature, $features)) {
                $features[$override->feature] = $override->value['value'] ?? null;
            }
        }

        return $features;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ProductFeature>  $catalog
     * @param  array<string, mixed>  $features
     * @return array<string, mixed>
     */
    protected function downgradeToReadOnly($catalog, array $features): array
    {
        foreach ($catalog as $feature) {
            // vypneme prepínače a limity zrazíme na nulu (nič nové sa nepridá)
            $features[$feature->key] = $feature->isFlag() ? false : 0;
        }

        return $features;
    }

    /**
     * @return array<string, int>
     */
    protected function currentUsage(Organization $organization, Product $product): array
    {
        return UsageReport::query()
            ->where('organization_id', $organization->id)
            ->where('product_id', $product->id)
            ->get()
            ->mapWithKeys(fn (UsageReport $report) => [$report->metric => $report->value])
            ->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ProductFeature>  $catalog
     * @param  array<string, mixed>  $features
     * @param  array<string, int>  $usage
     * @return array<string, array{limit: int|null, used: int}>
     */
    protected function overLimit($catalog, array $features, array $usage): array
    {
        $over = [];

        foreach ($catalog as $feature) {
            if (! $feature->isLimit() || ! $feature->metric) {
                continue;
            }

            $limit = $features[$feature->key] ?? null;

            if ($limit === null) {
                continue; // neobmedzene
            }

            $used = $usage[$feature->metric] ?? 0;

            if ($used > $limit) {
                $over[$feature->key] = ['limit' => (int) $limit, 'used' => $used];
            }
        }

        return $over;
    }

    protected function cacheKey(Organization $organization, Product $product): string
    {
        return "entitlements:{$organization->uuid}:{$product->key}";
    }

    /** Vráti stav, keď firma nie je k projektu vôbec naviazaná. */
    /** @return array<string, mixed> */
    public function unlinked(Product $product, ?string $organizationUuid = null): array
    {
        return [
            'product' => $product->key,
            'organization' => $organizationUuid,
            'plan' => null,
            'plan_name' => null,
            'status' => SubscriptionStatus::Cancelled->value,
            'access' => false,
            'read_only' => false,
            'features' => [],
            'usage' => [],
            'over_limit' => [],
            'trial_ends_at' => null,
            'current_period_end' => null,
            'grace_ends_at' => null,
            'resolved_at' => now()->toIso8601String(),
        ];
    }
}
