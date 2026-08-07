<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductFeature;
use App\Models\ServiceClient;
use App\Models\User;
use App\Services\Billing\SubscriptionManager;
use App\Services\Usage\UsageRecorder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->operator();
        $products = $this->products();
        $this->demoData($products);

        // Doklady vo všetkých stavoch – vrátane dobropisu, storna a reverse charge.
        $this->call(InvoiceSeeder::class);
    }

    protected function operator(): void
    {
        $email = env('SEED_ADMIN_EMAIL', 'admin@account.local');
        $password = env('SEED_ADMIN_PASSWORD', 'password');

        User::updateOrCreate(
            ['email' => $email],
            ['name' => 'Správca', 'password' => $password, 'email_verified_at' => now()],
        );

        $this->command?->info("Prihlásenie: {$email} / {$password}");
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
                'key' => 'projekt-1',
                'name' => 'Projekt 1',
                'url' => 'http://projekt1.local',
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
            [
                'key' => 'projekt-2',
                'name' => 'Projekt 2',
                'url' => 'http://projekt2.local',
                'features' => [
                    ['key' => 'max_projects', 'name' => 'Počet projektov', 'type' => 'limit', 'unit' => 'projektov', 'metric' => 'projects', 'default' => 3],
                    ['key' => 'api', 'name' => 'Prístup k API', 'type' => 'flag', 'default' => false],
                ],
                'plans' => [
                    ['key' => 'basic', 'name' => 'Basic', 'price' => 1900, 'trial' => 0, 'features' => ['max_projects' => 3, 'api' => false]],
                    ['key' => 'team', 'name' => 'Team', 'price' => 4900, 'trial' => 14, 'features' => ['max_projects' => 25, 'api' => true]],
                ],
            ],
            [
                'key' => 'projekt-3',
                'name' => 'Projekt 3',
                'url' => 'http://projekt3.local',
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

            if ($product->serviceClients()->count() === 0) {
                [, $plain] = ServiceClient::issue($product, 'lokálny vývoj');
                $this->command?->line("  {$product->name}: {$plain}");
            }

            $products[$definition['key']] = $product;
        }

        return $products;
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
        $ukazka = Organization::firstOrCreate(
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

        // projekt-1: aktívny Standard, blízko limitu
        $ukazka->linkTo($products['projekt-1']);
        $plan = Plan::whereRelation('product', 'key', 'projekt-1')->where('key', 'standard')->first();
        $subscription = $manager->subscribe($ukazka, $plan);
        $manager->activate($subscription, 'seed');
        $usage->record($ukazka, $products['projekt-1'], ['records' => 460]);

        // projekt-2: po splatnosti, beží grace perióda
        $ukazka->linkTo($products['projekt-2']);
        $plan = Plan::whereRelation('product', 'key', 'projekt-2')->where('key', 'team')->first();
        $subscription = $manager->subscribe($ukazka, $plan);
        $manager->markPastDue($subscription, 'seed');
        $usage->record($ukazka, $products['projekt-2'], ['projects' => 12]);

        // projekt-3: nad limitom po znížení plánu
        $ukazka->linkTo($products['projekt-3']);
        $plan = Plan::whereRelation('product', 'key', 'projekt-3')->where('key', 'starter')->first();
        $subscription = $manager->subscribe($ukazka, $plan);
        $manager->activate($subscription, 'seed');
        $usage->record($ukazka, $products['projekt-3'], ['users' => 8, 'storage_mb' => 220]);

        // Druhá firma len v jednom projekte
        $mala = Organization::firstOrCreate(
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

        $mala->linkTo($products['projekt-1']);
        $plan = Plan::whereRelation('product', 'key', 'projekt-1')->where('key', 'free')->first();
        $manager->subscribe($mala, $plan);
        $usage->record($mala, $products['projekt-1'], ['records' => 3]);

        $this->command?->newLine();
        $this->command?->info('Demo: Ukážka s.r.o. používa všetky tri projekty v rôznych stavoch.');
    }
}
