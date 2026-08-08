<?php

namespace App\Http\Controllers;

use App\Enums\SubscriptionStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Subscription;
use App\Services\Invoicing\InvoiceStatistics;
use App\Services\Usage\UsageRecorder;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Prehľad pre prevádzkovateľa – nie pre zákazníka.
 */
class DashboardController extends Controller
{
    public function __invoke(UsageRecorder $usage, InvoiceStatistics $statistics): Response
    {
        $products = Product::withCount('organizations')->get();

        $byStatus = Subscription::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        // Mesačný opakovaný príjem z aktívnych predplatných
        $mrrCents = Subscription::query()
            ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::PastDue->value])
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->selectRaw("sum(case when plans.interval = 'year' then plans.price_cents / 12 else plans.price_cents end * subscriptions.quantity) as total")
            ->value('total') ?? 0;

        $attention = Subscription::with(['organization', 'product', 'plan'])
            ->whereIn('status', [
                SubscriptionStatus::PastDue->value,
                SubscriptionStatus::Suspended->value,
            ])
            ->orderBy('grace_ends_at')
            ->limit(10)
            ->get()
            ->map(fn (Subscription $s) => [
                'organization' => $s->organization->name,
                'organization_id' => $s->organization->uuid,
                'product' => $s->product->name,
                'status' => $s->status->value,
                'status_label' => $s->status->label(),
                'deadline' => ($s->grace_ends_at ?? $s->suspended_until)?->toDateString(),
            ]);

        // Kto sa blíži k limitu – podklad na upsell
        $nearLimit = collect($products)
            ->flatMap(fn (Product $product) => collect($usage->nearLimit($product))
                ->map(fn (array $row) => [
                    'organization' => $row['organization']->name,
                    'organization_id' => $row['organization']->uuid,
                    'product' => $product->name,
                    'feature' => $row['feature']->name,
                    'used' => $row['used'],
                    'limit' => $row['limit'],
                    'unit' => $row['feature']->unit,
                    'ratio' => $row['ratio'],
                ]))
            ->sortByDesc('ratio')
            ->take(10)
            ->values();

        return Inertia::render('Dashboard', [
            'stats' => [
                'organizations' => Organization::count(),
                'products' => $products->count(),
                'active' => (int) ($byStatus[SubscriptionStatus::Active->value] ?? 0),
                'trialing' => (int) ($byStatus[SubscriptionStatus::Trialing->value] ?? 0),
                'past_due' => (int) ($byStatus[SubscriptionStatus::PastDue->value] ?? 0),
                'suspended' => (int) ($byStatus[SubscriptionStatus::Suspended->value] ?? 0),
                'mrr' => number_format($mrrCents / 100, 2, ',', ' ').' €',
            ],
            'products' => $products->map(fn (Product $product) => [
                'key' => $product->key,
                'name' => $product->name,
                'url' => $product->url,
                'is_active' => $product->is_active,
                'organizations_count' => $product->organizations_count,
            ]),
            'attention' => $attention,
            'near_limit' => $nearLimit,

            // Fakturácia: čo sa tento mesiac vystavilo, čo visí a čo príde.
            'invoicing' => $this->withMoney($statistics->summary()),
            'forecast' => $this->withMoney($statistics->forecast()),
            'months' => $statistics->months(),
        ]);
    }

    /**
     * Doplní ku každej sume v centoch aj naformátovanú podobu.
     *
     * Formátovanie patrí na server: v šablóne by sa skôr či neskôr
     * objavilo v troch mierne odlišných variantoch.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function withMoney(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value) && array_key_exists('cents', $value)) {
                $data[$key]['formatted'] = $this->money($value['cents']);
            }

            if ($key === 'total_cents') {
                $data['total'] = $this->money((int) $value);
            }
        }

        return $data;
    }

    protected function money(int $cents): string
    {
        return number_format($cents / 100, 2, ',', ' ').' €';
    }
}
