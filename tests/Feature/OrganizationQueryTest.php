<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use App\Repositories\OrganizationQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_filter_podla_projektu_vrati_len_jeho_firmy(): void
    {
        $event = Product::factory()->create(['key' => 'event']);
        $other = Product::factory()->create(['key' => 'projekt-2']);

        $mine = Organization::factory()->create(['name' => 'Kultúrny dom']);
        $mine->linkTo($event);

        $theirs = Organization::factory()->create(['name' => 'Cudzia firma']);
        $theirs->linkTo($other);

        Organization::factory()->create(['name' => 'Bez projektu']);

        $names = (new OrganizationQuery(['product' => 'event']))->builder()->pluck('name');

        $this->assertSame(['Kultúrny dom'], $names->all());
    }

    public function test_filter_bez_projektu_najde_osamotene_firmy(): void
    {
        $product = Product::factory()->create(['key' => 'event']);

        Organization::factory()->create(['name' => 'Naviazaná'])->linkTo($product);
        Organization::factory()->create(['name' => 'Osamotená']);

        $this->assertSame(
            ['Osamotená'],
            (new OrganizationQuery(['linked' => 'none']))->builder()->pluck('name')->all(),
        );

        $this->assertSame(
            ['Naviazaná'],
            (new OrganizationQuery(['linked' => 'any']))->builder()->pluck('name')->all(),
        );
    }

    public function test_hladanie_ide_cez_nazov_obchodne_meno_aj_ico(): void
    {
        Organization::factory()->create(['name' => 'Divadlo Aréna', 'legal_name' => 'Aréna n.o.', 'ico' => '11112222']);
        Organization::factory()->create(['name' => 'Kino Lumière', 'legal_name' => 'Lumière s.r.o.', 'ico' => '33334444']);

        $this->assertSame(['Divadlo Aréna'], (new OrganizationQuery(['q' => 'aréna']))->builder()->pluck('name')->all());
        $this->assertSame(['Kino Lumière'], (new OrganizationQuery(['q' => '3333']))->builder()->pluck('name')->all());
    }

    public function test_neznamy_stav_sa_ignoruje_namiesto_prazdneho_vypisu(): void
    {
        Organization::factory()->create(['status' => 'active']);

        $query = new OrganizationQuery(['status' => 'nezmysel']);

        $this->assertSame([], $query->toArray());
        $this->assertSame(1, $query->builder()->count());
    }

    public function test_filter_podla_projektu_prebije_filter_bez_projektu(): void
    {
        // Obe podmienky naraz by nikdy nič nevrátili – v UI sa navzájom
        // vypínajú, tu ide o poistku proti ručne zostavenej adrese.
        $query = new OrganizationQuery(['product' => 'event', 'linked' => 'none']);

        $this->assertSame(['product' => 'event'], $query->toArray());
    }

    public function test_prazdne_filtre_sa_do_adresy_nevracaju(): void
    {
        $query = new OrganizationQuery(['q' => '  ', 'status' => '', 'product' => null]);

        $this->assertTrue($query->isEmpty());
        $this->assertSame([], $query->toArray());
    }

    public function test_for_product_prijme_model_aj_kluc(): void
    {
        $product = Product::factory()->create(['key' => 'event']);

        $this->assertSame(['product' => 'event'], (new OrganizationQuery)->forProduct($product)->toArray());
        $this->assertSame(['product' => 'event'], (new OrganizationQuery)->forProduct('event')->toArray());

        // Nemennosť: pôvodná inštancia sa zúžením nesmie zmeniť.
        $base = new OrganizationQuery(['q' => 'test']);
        $narrowed = $base->forProduct('event');

        $this->assertSame(['q' => 'test'], $base->toArray());
        $this->assertSame(['q' => 'test', 'product' => 'event'], $narrowed->toArray());
    }

    public function test_zoznam_v_administracii_filtruje_podla_projektu(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['key' => 'event']);

        Organization::factory()->create(['name' => 'Patrí eventu'])->linkTo($product);
        Organization::factory()->create(['name' => 'Nepatrí nikomu']);

        $response = $this->actingAs($user)->get('/organizations?product=event');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Organizations/Index')
            ->where('filters.product', 'event')
            ->has('organizations.data', 1)
            ->where('organizations.data.0.name', 'Patrí eventu')
            ->has('products'));
    }
}
