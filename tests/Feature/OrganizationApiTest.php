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

    /** Premenovanie v projekte, ktorý sa iba naviazal na existujúcu firmu, sa musí prejaviť aj v Accounte. */
    public function test_napojenie_na_existujucu_firmu_aktualizuje_nazov(): void
    {
        $productA = Product::factory()->create(['key' => 'projekt-1']);
        $productB = Product::factory()->create(['key' => 'projekt-2']);

        [, $tokenA] = ServiceClient::issue($productA, 'test');
        [, $tokenB] = ServiceClient::issue($productB, 'test');

        $this->withToken($tokenA)
            ->postJson('/api/v1/organizations', ['name' => 'Pôvodný názov s.r.o.', 'ico' => '31333532'])
            ->assertCreated();

        $second = $this->withToken($tokenB)
            ->postJson('/api/v1/organizations', ['name' => 'Nový názov s.r.o.', 'ico' => '31333532']);

        $second->assertOk()->assertJsonPath('created', false);
        $second->assertJsonPath('data.name', 'Nový názov s.r.o.');

        $this->assertSame(1, Organization::count());
        $this->assertSame('Nový názov s.r.o.', Organization::first()->name);
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

    /**
     * Projekt pošle nevyplnené polia ako null – čo pravidlá dovoľujú.
     * Stĺpce s databázovým defaultom to však neprijmú, takže bez
     * odfiltrovania skončí zápis na 500 namiesto založenia firmy.
     */
    public function test_nevyplnene_polia_s_defaultom_neposlu_null_do_databazy(): void
    {
        $product = Product::factory()->create();
        [, $token] = ServiceClient::issue($product, 'test');

        $response = $this->withToken($token)->postJson('/api/v1/organizations', [
            'name' => 'Prázdny formulár s.r.o.',
            'vat_mode' => null,
            'country' => null,
            'currency' => null,
            'payment_method' => null,
            'payment_terms_days' => null,
            'invoice_language' => null,
            'invoice_delivery' => null,
        ]);

        $response->assertCreated();

        // Odpoveď musí hlásiť, čo je naozaj uložené – projekt si ju cachuje.
        $response->assertJsonPath('data.address.country', 'SK');
        $response->assertJsonPath('data.billing.currency', 'EUR');

        $organization = Organization::firstWhere('name', 'Prázdny formulár s.r.o.');

        $this->assertSame('non_payer', $organization->vat_mode->value);
        $this->assertSame('SK', $organization->country);
        $this->assertSame('EUR', $organization->currency);
        $this->assertSame(14, $organization->payment_terms_days);
    }

    /**
     * Občan nikdy nebude mať IČO. Bez tejto výnimky by mu doklad navždy
     * hlásil chýbajúci údaj a nedal by sa vystaviť.
     */
    public function test_sukromnej_osobe_sa_ico_nevyzaduje(): void
    {
        $product = Product::factory()->create();
        [, $token] = ServiceClient::issue($product, 'test');

        $response = $this->withToken($token)->postJson('/api/v1/organizations', [
            'name' => 'Ján Novák',
            'subject_type' => 'person',
            'street' => 'Hlavná',
            'street_no' => '1',
            'city' => 'Trenčín',
            'postal_code' => '91101',
            'billing_email' => 'jan@novak.sk',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.is_person', true);
        // Meno + adresa + e-mail stačia. Firme by tu ešte chýbalo IČO.
        $response->assertJsonPath('data.billing.missing', []);
    }

    /** Prepnutie na osobu musí firemné údaje zahodiť, nie ich nechať visieť. */
    public function test_prepnutie_na_osobu_vycisti_firemne_udaje(): void
    {
        $organization = Organization::factory()->create([
            'ico' => '31333532',
            'dic' => '2020317068',
            'register_court' => 'Mestský súd Bratislava III',
            'vat_mode' => 'payer',
        ]);

        $organization->update(['subject_type' => 'person']);

        $fresh = $organization->fresh();

        $this->assertNull($fresh->ico);
        $this->assertNull($fresh->dic);
        $this->assertNull($fresh->register_court);
        $this->assertSame('non_payer', $fresh->vat_mode->value);
        $this->assertFalse($fresh->isVatPayer());
    }

    /** Vyplnená hodnota sa odfiltrovaním nesmie stratiť. */
    public function test_vyplnene_polia_s_defaultom_sa_ulozia(): void
    {
        $product = Product::factory()->create();
        [, $token] = ServiceClient::issue($product, 'test');

        $this->withToken($token)->postJson('/api/v1/organizations', [
            'name' => 'Platiteľ s.r.o.',
            'vat_mode' => 'payer',
            'country' => 'CZ',
            'currency' => 'CZK',
        ])->assertCreated();

        $organization = Organization::firstWhere('name', 'Platiteľ s.r.o.');

        $this->assertSame('payer', $organization->vat_mode->value);
        $this->assertSame('CZ', $organization->country);
        $this->assertSame('CZK', $organization->currency);
    }
}
