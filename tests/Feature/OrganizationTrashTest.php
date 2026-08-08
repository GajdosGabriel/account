<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kôš organizácií: zmazanie, návrat a trvalé odstránenie.
 *
 * Celý tok ide cez HTTP zámerne – chyba sa tu nerodí v policy, ale
 * v tom, či route vôbec vie nabindovať zmazaný záznam.
 */
class OrganizationTrashTest extends TestCase
{
    use RefreshDatabase;

    protected function operator(): User
    {
        return User::factory()->create();
    }

    protected function organization(): Organization
    {
        return Organization::create(['name' => 'Skúšobná firma', 'status' => 'active']);
    }

    public function test_zmazana_firma_je_vo_filtri_kos(): void
    {
        $organization = $this->organization();

        $this->actingAs($this->operator())
            ->delete("/organizations/{$organization->uuid}")
            ->assertRedirect();

        $this->assertSoftDeleted($organization);

        $this->actingAs($this->operator())
            ->get('/organizations?status=trashed')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('trashed', true)
                ->where('organizations.data.0.name', 'Skúšobná firma')
                ->where('organizations.data.0.can.restore', true));
    }

    public function test_bezny_zoznam_zmazanu_firmu_neukazuje(): void
    {
        $organization = $this->organization();
        $organization->delete();

        $this->actingAs($this->operator())
            ->get('/organizations')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('organizations.data', []));
    }

    public function test_firmu_z_kosa_vie_operator_obnovit(): void
    {
        $organization = $this->organization();
        $organization->delete();

        $this->actingAs($this->operator())
            ->post("/organizations/{$organization->uuid}/restore")
            ->assertRedirect();

        $this->assertNotSoftDeleted($organization);
    }

    public function test_firmu_bez_dokladov_vie_operator_odstranit_natrvalo(): void
    {
        $organization = $this->organization();
        $organization->delete();

        $this->actingAs($this->operator())
            ->delete("/organizations/{$organization->uuid}/force")
            ->assertRedirect();

        $this->assertDatabaseMissing('organizations', ['id' => $organization->id]);
    }

    public function test_firma_v_kosi_sa_da_otvorit_na_upravu(): void
    {
        $organization = $this->organization();
        $organization->delete();

        $this->actingAs($this->operator())
            ->get("/organizations/{$organization->uuid}/edit")
            ->assertOk();
    }
}
