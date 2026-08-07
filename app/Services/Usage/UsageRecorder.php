<?php

namespace App\Services\Usage;

use App\Models\Organization;
use App\Models\Product;
use App\Models\UsageReport;
use App\Services\Entitlements\EntitlementService;

/**
 * Prijíma hlásenia o spotrebe z projektov.
 *
 * Držíme vždy iba poslednú hodnotu pre kombináciu firma + projekt + metrika.
 * História nie je cieľom – ide o to vedieť, kto je blízko limitu.
 */
class UsageRecorder
{
    public function __construct(private readonly EntitlementService $entitlements) {}

    /**
     * @param  array<string, int|string>  $metrics
     * @return array<string, int>
     */
    public function record(Organization $organization, Product $product, array $metrics): array
    {
        $known = $product->features()
            ->whereNotNull('metric')
            ->pluck('metric')
            ->all();

        $saved = [];

        foreach ($metrics as $metric => $value) {
            // Neznáme metriky ticho ignorujeme – projekt môže poslať viac,
            // než máme v katalógu, a nechceme kvôli tomu vracať chybu.
            if (! in_array($metric, $known, true)) {
                continue;
            }

            UsageReport::updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'product_id' => $product->id,
                    'metric' => $metric,
                ],
                [
                    'value' => max(0, (int) $value),
                    'reported_at' => now(),
                ],
            );

            $saved[$metric] = (int) $value;
        }

        if ($saved !== []) {
            $this->entitlements->flush($organization, $product);
        }

        return $saved;
    }

    /**
     * Firmy, ktoré sa blížia k limitu alebo ho prekročili –
     * podklad pre upozornenia a upsell.
     *
     * @return array<int, array<string, mixed>>
     */
    public function nearLimit(Product $product, float $threshold = 0.8): array
    {
        $rows = [];
        $features = $product->features()->where('type', 'limit')->whereNotNull('metric')->get();

        foreach ($product->organizations()->get() as $organization) {
            $resolved = $this->entitlements->for($organization, $product);

            foreach ($features as $feature) {
                $limit = $resolved['features'][$feature->key] ?? null;

                if ($limit === null || $limit <= 0) {
                    continue;
                }

                $used = $resolved['usage'][$feature->metric] ?? 0;
                $ratio = $used / $limit;

                if ($ratio >= $threshold) {
                    $rows[] = [
                        'organization' => $organization,
                        'feature' => $feature,
                        'limit' => (int) $limit,
                        'used' => $used,
                        'ratio' => round($ratio, 2),
                    ];
                }
            }
        }

        usort($rows, fn ($a, $b) => $b['ratio'] <=> $a['ratio']);

        return $rows;
    }
}
