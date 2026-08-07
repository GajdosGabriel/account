<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Product;
use App\Models\ServiceClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_zalozenie_firmy_podla_ico_nevytvori_duplikat(): void
    {
        $productA = Product::factory()->create(['key' => 'projekt-1']);
        $productB = Product::factory()->create(['key' => 'projekt-2']);

        [, $tokenA] = ServiceClient::issue($productA, 'test');
        [, $tokenB] = ServiceClient::issue($productB, 'test');

        $payload = ['name' => 'Ukážka s.r.o.', 'ico' => '31333532'];

        $first = $this->withToken($tokenA)->postJson('/api/v1/organizations', $payload);
        $first->assertCreated()->assertJsonPath('created', true);

        // Druhý projekt pošle tú istú firmu – má sa iba naviazať
        $second = $this->withToken($tokenB)->postJson('/api/v1/organizations', $payload);
        $second->assertOk()->assertJsonPath('created', false);

        $this->assertSame(1, Organization::count());
        $this->assertSame(
            $first->json('data.id'),
            $second->json('data.id'),
            'Obe volania musia ukazovať na tú istú firmu.',
        );
    }

    public function test_projekt_nevidi_firmy_ineho_projektu(): void
    {
        $mine = Product::factory()->create(['key' => 'projekt-1']);
        $other = Product::factory()->create(['key' => 'projekt-2']);

        [, $token] = ServiceClient::issue($mine, 'test');

        $organization = Organization::factory()->create();
        $organization->linkTo($other);

        $this->withToken($token)
            ->getJson("/api/v1/organizations/{$organization->uuid}")
            ->assertNotFound();
    }

    public function test_neplatne_ico_vrati_validacnu_chybu(): void
    {
        $product = Product::factory()->create();
        [, $token] = ServiceClient::issue($product, 'test');

        $this->withToken($token)
            ->postJson('/api/v1/organizations', ['name' => 'Test', 'ico' => '12345678'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ico');
    }

    public function test_bez_tokenu_je_api_zavrete(): void
    {
        $organization = Organization::factory()->create();

        $this->getJson("/api/v1/organizations/{$organization->uuid}")->assertUnauthorized();
    }
}
