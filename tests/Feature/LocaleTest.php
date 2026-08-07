<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ServiceClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Validačné chyby z API vidí koncový zákazník vo svojom projekte,
 * preto sa jazyk musí riadiť ním – nie predvoleným jazykom Accountu.
 */
class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_chyba_pride_v_jazyku_z_hlavicky(): void
    {
        $token = $this->token();

        $response = $this->withToken($token)
            ->withHeader('Accept-Language', 'de-DE,de;q=0.9')
            ->postJson('/api/v1/organizations', ['name' => 'Test', 'ico' => '12345678']);

        $response->assertStatus(422);

        $this->assertStringContainsString(
            'Prüfziffer',
            $response->json('errors.ico.0'),
            'Pri Accept-Language: de má prísť nemecká hláška.',
        );
    }

    public function test_parameter_lang_prebije_hlavicku(): void
    {
        $token = $this->token();

        $response = $this->withToken($token)
            ->withHeader('Accept-Language', 'de')
            ->postJson('/api/v1/organizations?lang=cs', ['name' => 'Test', 'ico' => '12345678']);

        $response->assertStatus(422);
        $this->assertStringContainsString('kontrolní číslice', $response->json('errors.ico.0'));
    }

    public function test_nepodporovany_jazyk_spadne_na_predvoleny(): void
    {
        $token = $this->token();

        $response = $this->withToken($token)
            ->withHeader('Accept-Language', 'fr-FR')
            ->postJson('/api/v1/organizations', ['name' => 'Test', 'ico' => '12345678']);

        $response->assertStatus(422);
        $this->assertStringContainsString('kontrolná číslica', $response->json('errors.ico.0'));
    }

    public function test_nazvy_poli_su_prelozene(): void
    {
        $token = $this->token();

        $response = $this->withToken($token)
            ->withHeader('Accept-Language', 'en')
            ->postJson('/api/v1/organizations', []);

        $response->assertStatus(422);
        // atribút `name` musí byť pomenovaný, nie surový kľúč
        $this->assertStringContainsString('name', strtolower($response->json('errors.name.0')));
    }

    protected function token(): string
    {
        [, $plain] = ServiceClient::issue(Product::factory()->create(), 'test');

        return $plain;
    }
}
