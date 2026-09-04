<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Product;
use App\Models\ServiceClient;
use App\Models\WebhookEndpoint;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pripojenie projektu k Accountu stojí na tom, že pravda je v `.env`, nie v
 * databáze — inak by každý reseed zabil spojenie. Tento test to stráži.
 *
 * Kontroluje sa aj kolízia, ktorá to raz už rozbila: keby demo časť seedera
 * vydala pripojenému projektu vlastný náhodný token ako prvá, poistka „už
 * jeden token má" by neskôr zabránila registrácii pevného tokenu zo `.env` a
 * projekt by dostával 401 s úplne správnou konfiguráciou.
 */
class ConnectedProjectsSeedTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'acc_pevnytokenanonymizera000000000000000000000000';

    private const SECRET = 'whsec_tajomstvoanonymizera00000000000000000000000';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('accounts.seed_admin.email', 'operator@test.local');
        config()->set('accounts.seed_admin.password', 'tajne-heslo');

        // Laravel má v testoch vypnutý putenv adaptér, takže samotné putenv()
        // by env() neovplyvnilo — hodnota musí byť aj v $_ENV/$_SERVER.
        $this->setEnv([
            'ANONYMIZER_URL' => 'https://anonymizer.test',
            'ANONYMIZER_SERVICE_TOKEN' => self::TOKEN,
            'ANONYMIZER_WEBHOOK_URL' => 'https://anonymizer.test/api/webhooks/account',
            'ANONYMIZER_WEBHOOK_SECRET' => self::SECRET,
        ]);
    }

    protected function tearDown(): void
    {
        foreach (['ANONYMIZER_URL', 'ANONYMIZER_SERVICE_TOKEN', 'ANONYMIZER_WEBHOOK_URL', 'ANONYMIZER_WEBHOOK_SECRET'] as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }

        parent::tearDown();
    }

    /**
     * @param  array<string, string>  $values
     */
    private function setEnv(array $values): void
    {
        foreach ($values as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    public function test_seeder_zaregistruje_pevny_token_a_webhook_anonymizera(): void
    {
        $this->seed(DatabaseSeeder::class);

        $product = Product::where('key', 'anonymizer')->firstOrFail();

        $this->assertSame('https://anonymizer.test', $product->url);

        // Práve jeden token — ten zo `.env`, žiadny demo navyše.
        $clients = ServiceClient::where('product_id', $product->id)->get();
        $this->assertCount(1, $clients);
        $this->assertSame(hash('sha256', self::TOKEN), $clients->first()->token_hash);

        // Anonymizér si okrem firiem ťahá aj entitlements a hlási spotrebu.
        $this->assertEqualsCanonicalizing(
            ['organizations:read', 'organizations:write', 'entitlements:read', 'usage:write'],
            $clients->first()->abilities,
        );

        $endpoint = WebhookEndpoint::where('product_id', $product->id)->firstOrFail();
        $this->assertSame('https://anonymizer.test/api/webhooks/account', $endpoint->url);
        $this->assertSame(self::SECRET, $endpoint->secret);
        $this->assertTrue($endpoint->is_active);
    }

    public function test_opakovany_seed_nevyrobi_duplikat(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $product = Product::where('key', 'anonymizer')->firstOrFail();

        $this->assertSame(1, ServiceClient::where('product_id', $product->id)->count());
        $this->assertSame(1, WebhookEndpoint::where('product_id', $product->id)->count());
    }

    public function test_anonymizer_ma_tri_plany_a_free_nema_uradne_sekcie(): void
    {
        $this->seed(DatabaseSeeder::class);

        $product = Product::where('key', 'anonymizer')->firstOrFail();
        $plans = Plan::where('product_id', $product->id)->pluck('features', 'key');

        $this->assertEqualsCanonicalizing(['free', 'standard', 'pro'], $plans->keys()->all());

        // Free = bezplatné jadro. Úradné nadstavby vypnuté.
        $this->assertFalse($plans['free']['cases']);
        $this->assertFalse($plans['free']['registry']);
        $this->assertFalse($plans['free']['approvals']);
        $this->assertFalse($plans['free']['embed']);

        // Standard = spisy a podateľňa, schvaľovanie a widget až v Pro.
        $this->assertTrue($plans['standard']['cases']);
        $this->assertTrue($plans['standard']['registry']);
        $this->assertFalse($plans['standard']['approvals']);

        $this->assertTrue($plans['pro']['approvals']);
        $this->assertTrue($plans['pro']['embed']);
        $this->assertNull($plans['pro']['max_members'], 'null = neobmedzene, nikdy nie 0 ani -1.');
    }

    public function test_firma_bez_predplatneho_dostane_hodnoty_free(): void
    {
        $this->seed(DatabaseSeeder::class);

        $product = Product::where('key', 'anonymizer')->firstOrFail();
        $defaults = $product->features()->pluck('default_value', 'key');

        // Katalógové defaulty sa musia rovnať plánu Free — firma, ktorá ešte
        // žiadne predplatné nemá, inak dostane prázdny zoznam funkcií.
        $this->assertFalse($defaults['cases']['value']);
        $this->assertFalse($defaults['registry']['value']);
        $this->assertFalse($defaults['approvals']['value']);
        $this->assertFalse($defaults['embed']['value']);
        $this->assertSame(3, $defaults['max_members']['value']);
    }
}
