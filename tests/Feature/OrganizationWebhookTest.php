<?php

namespace Tests\Feature;

use App\Jobs\DeliverWebhook;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ServiceClient;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Udalosti o firmách smerom do projektov.
 *
 * Doručovanie samo sa tu nespúšťa – overuje sa, že udalosť vznikne a že
 * ju endpoint dostane podľa svojho odberu.
 */
class OrganizationWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_zalozenie_firmy_z_projektu_vyvola_organization_created(): void
    {
        Queue::fake();

        $product = Product::factory()->create(['key' => 'projekt-1']);
        [, $token] = ServiceClient::issue($product, 'test');
        $endpoint = $this->endpoint($product, ['organization.created']);

        $this->withToken($token)
            ->postJson('/api/v1/organizations', ['name' => 'Obec Modelovo', 'ico' => '31333532'])
            ->assertCreated();

        $delivery = WebhookDelivery::where('webhook_endpoint_id', $endpoint->id)->sole();

        $this->assertSame('organization.created', $delivery->event);
        $this->assertSame('Obec Modelovo', $delivery->payload['data']['organization']['name']);
        $this->assertSame('31333532', $delivery->payload['data']['organization']['identifiers']['ico']);

        Queue::assertPushed(DeliverWebhook::class);
    }

    public function test_naviazanie_na_existujucu_firmu_nehlasi_zalozenie(): void
    {
        Queue::fake();

        $first = Product::factory()->create(['key' => 'projekt-1']);
        $second = Product::factory()->create(['key' => 'projekt-2']);
        [, $tokenFirst] = ServiceClient::issue($first, 'test');
        [, $tokenSecond] = ServiceClient::issue($second, 'test');

        $this->withToken($tokenFirst)
            ->postJson('/api/v1/organizations', ['name' => 'Obec Modelovo', 'ico' => '31333532'])
            ->assertCreated();

        $endpoint = $this->endpoint($second, ['organization.created']);

        // Druhý projekt sa na tú istú firmu iba naviaže. Nová nevznikla,
        // takže hlásiť jej vznik by bola nepravda.
        $this->withToken($tokenSecond)
            ->postJson('/api/v1/organizations', ['name' => 'Obec Modelovo', 'ico' => '31333532'])
            ->assertOk()
            ->assertJsonPath('created', false);

        $this->assertSame(0, WebhookDelivery::where('webhook_endpoint_id', $endpoint->id)->count());
    }

    public function test_endpoint_dostane_len_udalosti_ktore_odobera(): void
    {
        Queue::fake();

        $product = Product::factory()->create();
        $endpoint = $this->endpoint($product, ['organization.deleted']);

        Organization::factory()->create()->linkTo($product);

        $this->assertSame(0, WebhookDelivery::where('webhook_endpoint_id', $endpoint->id)->count());
    }

    /** @param  array<int, string>  $events */
    private function endpoint(Product $product, array $events): WebhookEndpoint
    {
        return WebhookEndpoint::create([
            'product_id' => $product->id,
            'url' => 'https://projekt.test/api/webhooks/account',
            'events' => $events,
            'is_active' => true,
        ]);
    }
}
