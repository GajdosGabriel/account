<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Zivotny cyklus predplatneho
    |--------------------------------------------------------------------------
    |
    | active  -> plny pristup
    | past_due-> platba zlyhala, stale plny pristup pocas grace_days
    | suspended -> read-only + paywall, pocas suspended_days
    | cancelled -> pristup uzamknuty, data este existuju (export)
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Jazyky
    |--------------------------------------------------------------------------
    |
    | Validačné chyby z API vidí koncový zákazník v projekte, preto sa jazyk
    | riadi hlavičkou Accept-Language alebo parametrom ?lang.
    |
    */

    'locales' => ['sk', 'cs', 'de', 'en'],

    'grace_days' => (int) env('ACCOUNTS_GRACE_DAYS', 14),
    'suspended_days' => (int) env('ACCOUNTS_SUSPENDED_DAYS', 30),

    'tokens' => [
        'access_ttl_minutes' => (int) env('ACCOUNTS_ACCESS_TOKEN_TTL_MINUTES', 15),
        'refresh_ttl_days' => (int) env('ACCOUNTS_REFRESH_TOKEN_TTL_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Registre na overovanie firemnych udajov
    |--------------------------------------------------------------------------
    */

    'rpo' => [
        'enabled' => filter_var(env('ACCOUNTS_RPO_ENABLED', true), FILTER_VALIDATE_BOOL),
        'base_url' => rtrim((string) env('ACCOUNTS_RPO_BASE_URL', 'https://api.statistics.sk/rpo/v1'), '/'),
        'timeout' => 10,
    ],

    'vies' => [
        'enabled' => filter_var(env('ACCOUNTS_VIES_ENABLED', true), FILTER_VALIDATE_BOOL),
        'base_url' => rtrim((string) env('ACCOUNTS_VIES_BASE_URL', 'https://ec.europa.eu/taxation_customs/vies/rest-api'), '/'),
        'timeout' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhooky pre pripojene projekty
    |--------------------------------------------------------------------------
    */

    'webhooks' => [
        'max_attempts' => 8,
        // exponencialny backoff v sekundach podla poctu pokusov
        'backoff' => [30, 120, 600, 1800, 3600, 10800, 21600, 43200],
        'timeout' => 8,
        'signature_header' => 'X-Accounts-Signature',
        'timestamp_header' => 'X-Accounts-Timestamp',
    ],

    /*
    |--------------------------------------------------------------------------
    | Fakturacia
    |--------------------------------------------------------------------------
    */

    'billing' => [
        'currency' => 'EUR',
        'vat_rate' => 23.0,          // SR zakladna sadzba DPH od 1.1.2025
        'home_country' => 'SK',
        'invoice_due_days' => 14,
    ],

    /*
    |--------------------------------------------------------------------------
    | Prvý operátor
    |--------------------------------------------------------------------------
    |
    | Back-office nemá registráciu – /register neexistuje a ďalších operátorov
    | zakladá až prihlásený človek v Nastaveniach. Prvý účet preto vyrobí
    | seeder: php artisan db:seed --class=AdminUserSeeder
    |
    | Číta sa cez config, nie cez env() v seederi – na serveri beží
    | `config:cache` a tam by env() vrátilo null.
    |
    */

    'seed_admin' => [
        'name' => env('SEED_ADMIN_NAME', 'Správca'),
        'email' => env('SEED_ADMIN_EMAIL'),
        'password' => env('SEED_ADMIN_PASSWORD'),
    ],

];
