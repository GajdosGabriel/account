<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Obrazovky back-office majú texty v lang, nie v šablóne.
 *
 * Test drží dve veci: že sa preklady vôbec dostanú do props (bez nich by
 * `t()` vypísal holý kľúč) a že prepnutie jazyka naozaj zmení obsah.
 */
class BackOfficeLocaleTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, string> */
    private const PAGES = [
        '/dashboard',
        '/organizations',
        '/organizations/create',
        '/invoices',
    ];

    public function test_stranky_posielaju_preklady_do_props(): void
    {
        $user = User::factory()->create();

        foreach (self::PAGES as $url) {
            $response = $this->actingAs($user)->get($url);

            $response->assertOk();

            foreach (['common', 'enums', 'dashboard', 'organizations', 'invoices'] as $group) {
                $this->assertIsArray(
                    $response->viewData('page')['props']['translations'][$group] ?? null,
                    "Stránke $url chýba skupina prekladov `$group`.",
                );
            }
        }
    }

    public function test_jazyk_z_hlavicky_zmeni_texty_stranky(): void
    {
        $user = User::factory()->create();

        $expected = [
            'sk' => 'Prehľad',
            'cs' => 'Přehled',
            'de' => 'Übersicht',
            'en' => 'Overview',
        ];

        foreach ($expected as $locale => $title) {
            $props = $this->actingAs($user)
                ->withHeader('Accept-Language', $locale)
                ->get('/dashboard')
                ->assertOk()
                ->viewData('page')['props'];

            $this->assertSame($title, $props['translations']['dashboard']['title']);
        }
    }

    /**
     * Popisky číselníkov chodia zo servera už preložené – StatusBadge ani
     * filter si ich nedopĺňajú z vlastnej kópie.
     */
    public function test_ciselniky_v_props_su_v_jazyku_poziadavky(): void
    {
        $props = $this->actingAs(User::factory()->create())
            ->withHeader('Accept-Language', 'de')
            ->get('/invoices')
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertContains(
            'Entwurf',
            array_column($props['statuses'], 'label'),
        );
    }
}
