<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductFeature;
use App\Models\ServiceClient;
use App\Models\WebhookEndpoint;
use App\Services\Billing\SubscriptionManager;
use App\Services\Usage\UsageRecorder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Projekty, ktoré sú naozaj pripojené — ich token aj webhookové tajomstvo
     * určuje `.env` Accountu a tá istá hodnota musí byť v `.env` projektu.
     *
     * Kľúč poľa je kľúč produktu; produkt samotný aj s funkciami a plánmi je
     * definovaný v products(), tu sa dopĺňa len pripojenie.
     *
     * @var array<string, array<string, mixed>>
     */
    protected const CONNECTED = [
        'samosprava' => [
            'name' => 'Samospráva',
            'url' => 'http://samosprava.local',
            'url_env' => 'SAMOSPRAVA_URL',
            'token_env' => 'SAMOSPRAVA_SERVICE_TOKEN',
            'webhook_url_env' => 'SAMOSPRAVA_WEBHOOK_URL',
            'webhook_secret_env' => 'SAMOSPRAVA_WEBHOOK_SECRET',
            'abilities' => ['organizations:read', 'organizations:write'],
            'events' => ['*'],
        ],
        'anonymizer' => [
            'name' => 'Anonymizer',
            'url' => 'http://anonymizer.local',
            'url_env' => 'ANONYMIZER_URL',
            'token_env' => 'ANONYMIZER_SERVICE_TOKEN',
            'webhook_url_env' => 'ANONYMIZER_WEBHOOK_URL',
            'webhook_secret_env' => 'ANONYMIZER_WEBHOOK_SECRET',
            // Anonymizér si ťahá aj entitlements (čo firma smie) a hlási
            // spotrebu (koľko má členov), preto má oproti Samospráve dve
            // oprávnenia navyše.
            'abilities' => ['organizations:read', 'organizations:write', 'entitlements:read', 'usage:write'],
            'events' => ['*'],
        ],
    ];

    public function run(): void
    {
        $this->call(AdminUserSeeder::class);
        $products = $this->products();
        $this->connectedProjects();
        $this->demoData($products);

        // Doklady vo všetkých stavoch – vrátane dobropisu, storna a reverse charge.
        $this->call(InvoiceSeeder::class);
    }

    /**
     * Tri projekty, každý s inými funkciami a inými limitmi –
     * presne tak, ako to má byť v praxi.
     *
     * @return array<string, Product>
     */
    protected function products(): array
    {
        $definitions = [
            [
                'key' => 'event',
                'name' => 'Event',
                'url' => 'http://event.local',
                'features' => [
                    ['key' => 'max_records', 'name' => 'Počet záznamov', 'type' => 'limit', 'unit' => 'záznamov', 'metric' => 'records', 'default' => 10],
                    ['key' => 'export', 'name' => 'Export do XLSX', 'type' => 'flag', 'default' => false],
                ],
                'plans' => [
                    ['key' => 'free', 'name' => 'Free', 'price' => 0, 'trial' => 0, 'features' => ['max_records' => 10, 'export' => false]],
                    ['key' => 'standard', 'name' => 'Standard', 'price' => 2900, 'trial' => 14, 'features' => ['max_records' => 500, 'export' => true]],
                    ['key' => 'pro', 'name' => 'Pro', 'price' => 7900, 'trial' => 14, 'features' => ['max_records' => null, 'export' => true]],
                ],
            ],
            // Anonymizér je freemium: Free nikoho nezamyká, iba nemá úradné sekcie.
            // Preto sa `default` hodnoty rovnajú plánu Free — firma, ktorá ešte
            // žiadne predplatné nemá, dostane presne Free, nie prázdny zoznam.
            [
                'key' => 'anonymizer',
                'name' => 'Anonymizer',
                'url' => env('ANONYMIZER_URL', 'http://anonymizer.local'),
                'features' => [
                    ['key' => 'cases', 'name' => 'Spisy', 'type' => 'flag', 'default' => false],
                    ['key' => 'registry', 'name' => 'Podateľňa a rozdeľovník', 'type' => 'flag', 'default' => false],
                    ['key' => 'approvals', 'name' => 'Schvaľovanie zverejnenia', 'type' => 'flag', 'default' => false],
                    ['key' => 'embed', 'name' => 'Widget a verejný feed', 'type' => 'flag', 'default' => false],
                    ['key' => 'max_members', 'name' => 'Počet členov organizácie', 'type' => 'limit', 'unit' => 'členov', 'metric' => 'members', 'default' => 3],
                ],
                'plans' => [
                    ['key' => 'free', 'name' => 'Free', 'price' => 0, 'trial' => 0, 'features' => [
                        'cases' => false, 'registry' => false, 'approvals' => false, 'embed' => false, 'max_members' => 3,
                    ]],
                    ['key' => 'standard', 'name' => 'Standard', 'price' => 2900, 'trial' => 14, 'features' => [
                        'cases' => true, 'registry' => true, 'approvals' => false, 'embed' => false, 'max_members' => 15,
                    ]],
                    ['key' => 'pro', 'name' => 'Pro', 'price' => 7900, 'trial' => 14, 'features' => [
                        'cases' => true, 'registry' => true, 'approvals' => true, 'embed' => true, 'max_members' => null,
                    ]],
                ],
            ],
            [
                'key' => 'samosprava',
                'name' => 'Samospráva',
                'url' => 'http://samosprava.local',
                'features' => [
                    ['key' => 'max_users', 'name' => 'Počet používateľov', 'type' => 'limit', 'unit' => 'používateľov', 'metric' => 'users', 'default' => 5],
                    ['key' => 'storage_mb', 'name' => 'Úložisko', 'type' => 'limit', 'unit' => 'MB', 'metric' => 'storage_mb', 'default' => 500],
                    ['key' => 'sso', 'name' => 'Jednotné prihlásenie', 'type' => 'flag', 'default' => false],
                ],
                'plans' => [
                    ['key' => 'starter', 'name' => 'Starter', 'price' => 0, 'trial' => 0, 'features' => ['max_users' => 5, 'storage_mb' => 500, 'sso' => false]],
                    ['key' => 'business', 'name' => 'Business', 'price' => 9900, 'trial' => 30, 'features' => ['max_users' => 20, 'storage_mb' => 10000, 'sso' => true]],
                ],
            ],
        ];

        $products = [];

        foreach ($definitions as $index => $definition) {
            $product = Product::updateOrCreate(
                ['key' => $definition['key']],
                ['name' => $definition['name'], 'url' => $definition['url'], 'is_active' => true],
            );

            foreach ($definition['features'] as $order => $feature) {
                ProductFeature::updateOrCreate(
                    ['product_id' => $product->id, 'key' => $feature['key']],
                    [
                        'name' => $feature['name'],
                        'type' => $feature['type'],
                        'unit' => $feature['unit'] ?? null,
                        'metric' => $feature['metric'] ?? null,
                        'default_value' => ['value' => $feature['default']],
                        'sort_order' => $order,
                    ],
                );
            }

            foreach ($definition['plans'] as $order => $plan) {
                Plan::updateOrCreate(
                    ['product_id' => $product->id, 'key' => $plan['key']],
                    [
                        'name' => $plan['name'],
                        'price_cents' => $plan['price'],
                        'interval' => 'month',
                        'trial_days' => $plan['trial'],
                        'features' => $plan['features'],
                        'is_active' => true,
                        'sort_order' => $order,
                    ],
                );
            }

            // Projektu s pevným tokenom demo token nevydávame. Inak by ho tu
            // dostal ako prvý, poistka „už jeden token má" by neskôr v
            // connectedProjects() zabrala a pevná hodnota zo `.env` by sa
            // nikdy nezaregistrovala — projekt by dostával 401, hoci má v
            // konfigurácii správny token.
            if (! array_key_exists($definition['key'], self::CONNECTED)
                && $product->serviceClients()->count() === 0) {
                [, $plain] = ServiceClient::issue($product, 'lokálny vývoj');
                $this->command?->line("  {$product->name}: {$plain}");
            }

            $products[$definition['key']] = $product;
        }

        return $products;
    }

    /**
     * Skutočné pripojené projekty (nie demo dáta) – ich token je pevný,
     * lebo musí sedieť s `ACCOUNT_TOKEN` v `.env` daného projektu.
     *
     * Pravda o tokene je v `.env`, nie v databáze. Preto databázu môžeš
     * kedykoľvek premazať a po seede sa tá istá hodnota zaregistruje znova.
     */
    protected function connectedProjects(): void
    {
        foreach (self::CONNECTED as $key => $project) {
            $product = Product::updateOrCreate(
                ['key' => $key],
                [
                    'name' => $project['name'],
                    'url' => env($project['url_env'], $project['url']),
                    'is_active' => true,
                ],
            );

            $this->fixedToken($product, $project);
            $this->webhookEndpoint($product, $project);
        }
    }

    /**
     * @param  array<string, mixed>  $project
     */
    protected function fixedToken(Product $product, array $project): void
    {
        $plain = env($project['token_env']);

        // Poistka proti duplicitám pri opakovanom seede. Pozor: práve kvôli nej
        // sa v products() demo token pripojeným projektom nevydáva.
        if (! $plain || $product->serviceClients()->whereNull('revoked_at')->exists()) {
            return;
        }

        ServiceClient::create([
            'product_id' => $product->id,
            'name' => "{$product->key} (pevný token)",
            'token_prefix' => substr($plain, 0, 12),
            'token_hash' => hash('sha256', $plain),
            'abilities' => $project['abilities'],
        ]);

        $this->command?->line("  {$product->name}: pevný token zo `.env` zaregistrovaný");
    }

    /**
     * Opačný smer: kam má Account posielať oznámenia o zmene predplatného.
     *
     * `secret` je to isté tajomstvo, ktoré projekt pozná ako
     * `ACCOUNT_WEBHOOK_SECRET` – bez zhody projekt podpis neoverí a webhook
     * zahodí ako podvrh.
     *
     * @param  array<string, mixed>  $project
     */
    protected function webhookEndpoint(Product $product, array $project): void
    {
        $url = env($project['webhook_url_env']);
        $secret = env($project['webhook_secret_env']);

        if (! $url || ! $secret) {
            return;
        }

        WebhookEndpoint::updateOrCreate(
            ['product_id' => $product->id, 'url' => $url],
            ['secret' => $secret, 'events' => $project['events'], 'is_active' => true],
        );

        $this->command?->line("  {$product->name}: webhook → {$url}");
    }

    /**
     * @param  array<string, Product>  $products
     */
    protected function demoData(array $products): void
    {
        $manager = app(SubscriptionManager::class);
        $usage = app(UsageRecorder::class);

        // Firma, ktorá používa všetky tri projekty – ukážka toho,
        // prečo sa firemné údaje centralizujú.
        $ukazka = Organization::withTrashed()->firstOrCreate(
            ['ico' => '31333532'],
            [
                'name' => 'Ukážka',
                'legal_name' => 'Ukážka, s. r. o.',
                'legal_form' => 'sro',
                'dic' => '2020123456',
                'ic_dph' => 'SK2020123456',
                'vat_mode' => 'payer',
                'oss_registered' => true,
                'register_court' => 'Okresný súd Bratislava I',
                'register_section' => 'Sro',
                'register_insert' => '12345/B',
                'established_at' => '2015-03-01',
                'street' => 'Hlavná',
                'street_no' => '1',
                'city' => 'Bratislava',
                'postal_code' => '81101',
                'region' => 'Bratislavský kraj',
                'country' => 'SK',
                'email' => 'info@ukazka.sk',
                'billing_email' => 'faktury@ukazka.sk',
                'phone' => '+421 900 000 000',
                'website' => 'https://ukazka.sk',
                'bank_name' => 'Tatra banka',
                'iban' => 'SK3112000000198742637541',
                'swift' => 'TATRSKBX',
                'currency' => 'EUR',
                'payment_terms_days' => 14,
            ],
        );

        // event: aktívny Standard, blízko limitu
        $ukazka->linkTo($products['event']);
        $plan = Plan::whereRelation('product', 'key', 'event')->where('key', 'standard')->first();
        $subscription = $manager->subscribe($ukazka, $plan);
        $manager->activate($subscription, 'seed');
        $usage->record($ukazka, $products['event'], ['records' => 460]);

        // anonymizer: po splatnosti, beží grace perióda
        $ukazka->linkTo($products['anonymizer']);
        $plan = Plan::whereRelation('product', 'key', 'anonymizer')->where('key', 'standard')->first();
        $subscription = $manager->subscribe($ukazka, $plan);
        $manager->markPastDue($subscription, 'seed');
        $usage->record($ukazka, $products['anonymizer'], ['members' => 9]);

        // samosprava: nad limitom po znížení plánu
        $ukazka->linkTo($products['samosprava']);
        $plan = Plan::whereRelation('product', 'key', 'samosprava')->where('key', 'starter')->first();
        $subscription = $manager->subscribe($ukazka, $plan);
        $manager->activate($subscription, 'seed');
        $usage->record($ukazka, $products['samosprava'], ['users' => 8, 'storage_mb' => 220]);

        // Druhá firma len v jednom projekte
        $mala = Organization::withTrashed()->firstOrCreate(
            ['ico' => '35815256'],
            [
                'name' => 'Malá firma',
                'legal_name' => 'Malá firma, s. r. o.',
                'legal_form' => 'sro',
                'vat_mode' => 'non_payer',
                'street' => 'Krátka',
                'street_no' => '5',
                'city' => 'Košice',
                'postal_code' => '04001',
                'country' => 'SK',
                'billing_email' => 'info@malafirma.sk',
                'payment_terms_days' => 30,
            ],
        );

        if ($ukazka->trashed()) {
            $ukazka->restore();
        }

        if ($mala->trashed()) {
            $mala->restore();
        }

        // Eshop má poštu inde než sídlo a k tomu dva sklady –
        // presne preto sú adresy vo vlastnej tabuľke.
        $ukazka->addresses()->firstOrCreate(
            ['type' => 'mailing', 'street' => 'P. O. Box'],
            [
                'recipient' => 'Ukážka, s. r. o. — účtáreň',
                'street_no' => '17',
                'city' => 'Bratislava 25',
                'postal_code' => '82005',
                'country' => 'SK',
                'is_default' => true,
            ],
        );

        $ukazka->addresses()->firstOrCreate(
            ['type' => 'delivery', 'label' => 'Sklad Bratislava'],
            [
                'recipient' => 'Príjem tovaru',
                'street' => 'Skladová',
                'street_no' => '12',
                'city' => 'Bratislava',
                'postal_code' => '82109',
                'country' => 'SK',
                'phone' => '+421 901 111 222',
                'note' => 'Príjem 7:00 – 15:00, rampa č. 3',
                'is_default' => true,
            ],
        );

        $ukazka->contacts()->firstOrCreate(
            ['email' => 'jan.novak@ukazka.sk'],
            [
                'type' => 'statutory',
                'name' => 'Ján Novák',
                'position' => 'konateľ',
                'phone' => '+421 903 123 456',
                'is_primary' => true,
            ],
        );

        $ukazka->contacts()->firstOrCreate(
            ['email' => 'uctaren@ukazka.sk'],
            ['type' => 'billing', 'name' => 'Eva Kováčová', 'position' => 'účtovníčka'],
        );

        $mala->linkTo($products['event']);
        $plan = Plan::whereRelation('product', 'key', 'event')->where('key', 'free')->first();
        $manager->subscribe($mala, $plan);
        $usage->record($mala, $products['event'], ['records' => 3]);

        $this->command?->newLine();
        $this->command?->info('Demo: Ukážka s.r.o. používa všetky tri projekty v rôznych stavoch.');
    }
}
