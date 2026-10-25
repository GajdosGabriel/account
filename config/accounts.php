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
    | Komunikácia s klientmi projektov
    |--------------------------------------------------------------------------
    |
    | Account posiela maily v mene projektov a číta odpovede z ich IMAP
    | schránok. Prílohy prichádzajúcich mailov idú na `attachments_disk`.
    |
    */

    'messaging' => [
        'attachments_disk' => env('MESSAGING_ATTACHMENTS_DISK', env('FILESYSTEM_DISK', 'local')),
        // SMTP schránky sa použije len na produkcii; inde ide pošta cez
        // MAIL_MAILER Accountu (lokálne Mailtrap), nikdy skutočným klientom
        'use_mailbox_smtp' => filter_var(env('MESSAGING_USE_MAILBOX_SMTP', env('APP_ENV') === 'production'), FILTER_VALIDATE_BOOL),
        // koľko správ zo schránky spracovať naraz
        'fetch_batch' => (int) env('MESSAGING_FETCH_BATCH', 50),
        'max_send_attempts' => 5,
        'send_backoff' => [60, 300, 900, 3600],
        'imap_timeout' => 30,
        // maximálna veľkosť prílohy uloženej z prichádzajúceho mailu (bajty)
        'max_attachment_size' => 15 * 1024 * 1024,
        // po koľkých dňoch od poslednej správy sa spam zmaže
        'spam_retention_days' => (int) env('MESSAGING_SPAM_RETENTION_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Hromadný emailing (ponuky a automatizácie)
    |--------------------------------------------------------------------------
    |
    | Plánovač každú minútu odošle najviac `batch_size` e-mailov. Limit za
    | hodinu platí pre každú schránku zvlášť – poskytovatelia SMTP obmedzujú
    | odosielateľa, nie Account. V `dry_run` prebehne všetko okrem samotného
    | odoslania (vykreslenie, meranie, stavy).
    |
    */

    'mailing' => [
        'enabled' => filter_var(env('MAILING_ENABLED', true), FILTER_VALIDATE_BOOL),
        'dry_run' => filter_var(env('MAILING_DRY_RUN', false), FILTER_VALIDATE_BOOL),
        'hourly_limit' => (int) env('MAILING_HOURLY_LIMIT', 100),
        'batch_size' => min(50, max(1, (int) env('MAILING_BATCH_SIZE', 10))),
        // Klik a otvorenie sa počítajú len pri správach mladších ako toto
        'measure_days' => 90,
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
