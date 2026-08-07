<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductFeature;
use App\Services\Billing\SubscriptionManager;
use App\Services\Entitlements\EntitlementService;
use App\Services\Usage\UsageRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntitlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_hodnota_sa_berie_z_planu_a_chybajuca_z_katalogu(): void
    {
        [$organization, $product, $plan] = $this->scenario(['max_records' => 50]);

        $resolved = app(EntitlementService::class)->for($organization, $product, fresh: true);

        $this->assertSame(50, $resolved['features']['max_records']);
        // export plán neuvádza, takže platí default z katalógu
        $this->assertFalse($resolved['features']['export']);
    }

    public function test_null_v_plane_znamena_neobmedzene(): void
    {
        [$organization, $product] = $this->scenario(['max_records' => null]);

        $resolved = app(EntitlementService::class)->for($organization, $product, fresh: true);

        $this->assertNull($resolved['features']['max_records']);
        // neobmedzený limit sa nikdy neoznačí ako prekročený
        $this->assertSame([], $resolved['over_limit']);
    }

    public function test_prekrocenie_limitu_sa_ohlasi_ale_nezamkne_pristup(): void
    {
        [$organization, $product] = $this->scenario(['max_records' => 10]);

        app(UsageRecorder::class)->record($organization, $product, ['records' => 15]);

        $resolved = app(EntitlementService::class)->for($organization, $product, fresh: true);

        $this->assertTrue($resolved['access'], 'Nad limitom sa prístup nezamyká.');
        $this->assertSame(['limit' => 10, 'used' => 15], $resolved['over_limit']['max_records']);
        $this->assertSame(15, $resolved['usage']['records']);
    }

    public function test_pozastavene_predplatne_vypne_funkcie(): void
    {
        [$organization, $product] = $this->scenario(['max_records' => 100, 'export' => true]);

        $manager = app(SubscriptionManager::class);
        $subscription = $organization->subscriptionFor($product);

        $manager->markPastDue($subscription);
        $manager->suspend($subscription->refresh());

        $resolved = app(EntitlementService::class)->for($organization, $product, fresh: true);

        $this->assertFalse($resolved['access']);
        $this->assertTrue($resolved['read_only']);
        $this->assertFalse($resolved['features']['export']);
        $this->assertSame(0, $resolved['features']['max_records']);
    }

    public function test_firma_bez_vazby_na_projekt_nema_pristup(): void
    {
        $product = Product::factory()->create(['key' => 'projekt-1']);
        $organization = Organization::factory()->create();

        $resolved = app(EntitlementService::class)->unlinked($product, $organization->uuid);

        $this->assertFalse($resolved['access']);
        $this->assertSame([], $resolved['features']);
    }

    public function test_neznama_metrika_sa_ignoruje(): void
    {
        [$organization, $product] = $this->scenario(['max_records' => 10]);

        $saved = app(UsageRecorder::class)->record($organization, $product, [
            'records' => 5,
            'vymyslena_metrika' => 999,
        ]);

        $this->assertSame(['records' => 5], $saved);
    }

    /**
     * @param  array<string, mixed>  $planFeatures
     * @return array{0: Organization, 1: Product, 2: Plan}
     */
    protected function scenario(array $planFeatures): array
    {
        $product = Product::factory()->create(['key' => 'projekt-1']);

        ProductFeature::factory()->for($product)->create([
            'key' => 'max_records',
            'metric' => 'records',
            'default_value' => ['value' => 10],
        ]);

        ProductFeature::factory()->for($product)->flag()->create([
            'default_value' => ['value' => false],
        ]);

        $plan = Plan::factory()->for($product)->create([
            'key' => 'standard',
            'features' => $planFeatures,
            'trial_days' => 0,
        ]);

        $organization = Organization::factory()->create();
        $organization->linkTo($product);

        app(SubscriptionManager::class)->subscribe($organization, $plan);

        return [$organization, $product, $plan];
    }
}
