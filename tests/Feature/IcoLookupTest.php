<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IcoLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_ico_najprv_hlada_v_databaze_a_register_nevola(): void
    {
        Http::preventStrayRequests();

        $organization = Organization::factory()->create([
            'ico' => '35697270',
            'name' => 'Existujúca s.r.o.',
            'city' => 'Košice',
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->postJson('/lookup/ico', ['ico' => '35697270']);

        $response->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('source', 'db')
            ->assertJsonPath('city', 'Košice')
            ->assertJsonPath('organization.uuid', $organization->uuid);
    }

    public function test_medzery_v_ico_nezabranit_najdeniu_v_databaze(): void
    {
        Http::preventStrayRequests();

        Organization::factory()->create(['ico' => '35697270']);

        $this->actingAs(User::factory()->create())
            ->postJson('/lookup/ico', ['ico' => '356 972 70'])
            ->assertOk()
            ->assertJsonPath('source', 'db');
    }

    public function test_pri_uprave_sa_firma_nehlasi_sama_sebe(): void
    {
        Http::fake([
            'api.statistics.sk/*' => Http::response(['results' => [[
                'fullNames' => [['value' => 'Orange Slovensko, a.s.']],
                'addresses' => [['street' => 'Metodova', 'buildingNumber' => '8']],
            ]]]),
            '*' => Http::response([], 500),
        ]);

        $organization = Organization::factory()->create(['ico' => '35697270']);

        $this->actingAs(User::factory()->create())
            ->postJson('/lookup/ico', ['ico' => '35697270', 'exclude' => $organization->uuid])
            ->assertOk()
            ->assertJsonPath('source', 'rpo')
            ->assertJsonPath('name', 'Orange Slovensko, a.s.');
    }

    public function test_neznamu_firmu_dotiahne_z_registra(): void
    {
        Http::fake([
            'api.statistics.sk/*' => Http::response(['results' => [[
                'fullNames' => [
                    ['value' => 'Staré meno, a.s.', 'validFrom' => '1996-09-03', 'validTo' => '2002-03-07'],
                    ['value' => 'Orange Slovensko, a.s.', 'validFrom' => '2002-03-08'],
                ],
                'addresses' => [['street' => 'Metodova', 'buildingNumber' => '8', 'municipality' => ['value' => 'Bratislava']]],
            ]]]),
            '*' => Http::response([], 500),
        ]);

        $this->actingAs(User::factory()->create())
            ->postJson('/lookup/ico', ['ico' => '35697270'])
            ->assertOk()
            ->assertJsonPath('source', 'rpo')
            // z historizovaného poľa musí prísť aktuálny názov, nie prvý
            ->assertJsonPath('name', 'Orange Slovensko, a.s.')
            ->assertJsonPath('city', 'Bratislava');
    }
}
