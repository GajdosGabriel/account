<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dodávateľ – tvoja firma
    |--------------------------------------------------------------------------
    |
    | Toto sa tlačí do ľavého stĺpca faktúry. Údaje sa v okamihu vystavenia
    | odfotia do stĺpca invoices.supplier_snapshot, takže neskoršia zmena
    | (napr. sťahovanie sídla) staré faktúry nijako neovplyvní.
    |
    */

    'supplier' => [
        'name' => env('INVOICE_SUPPLIER_NAME', 'Moja firma, s. r. o.'),
        'street' => env('INVOICE_SUPPLIER_STREET', 'Hlavná 1'),
        'city' => env('INVOICE_SUPPLIER_CITY', 'Bratislava'),
        'postal_code' => env('INVOICE_SUPPLIER_ZIP', '81101'),
        'country' => env('INVOICE_SUPPLIER_COUNTRY', 'SK'),

        'ico' => env('INVOICE_SUPPLIER_ICO', '12345678'),
        'dic' => env('INVOICE_SUPPLIER_DIC', '2020123456'),
        'ic_dph' => env('INVOICE_SUPPLIER_IC_DPH', 'SK2020123456'),

        // Prázdne IČ DPH => neplatiteľ; na faktúre sa potom netlačí DPH.
        'vat_payer' => filter_var(env('INVOICE_SUPPLIER_VAT_PAYER', true), FILTER_VALIDATE_BOOL),

        // Zápis v registri – na faktúre povinný údaj (§ 3a Obchodného zákonníka).
        'registration' => env(
            'INVOICE_SUPPLIER_REGISTRATION',
            'Okresný súd Bratislava I, oddiel Sro, vložka č. 12345/B',
        ),

        'email' => env('INVOICE_SUPPLIER_EMAIL', 'faktury@mojafirma.sk'),
        'phone' => env('INVOICE_SUPPLIER_PHONE', '+421 900 000 000'),
        'web' => env('INVOICE_SUPPLIER_WEB', 'https://mojafirma.sk'),

        'bank_name' => env('INVOICE_SUPPLIER_BANK', 'Tatra banka, a. s.'),
        'iban' => env('INVOICE_SUPPLIER_IBAN', 'SK3112000000198742637541'),
        'swift' => env('INVOICE_SUPPLIER_SWIFT', 'TATRSKBX'),

        // Kto doklad vystavil – tlačí sa dole pri podpise.
        'issued_by' => env('INVOICE_SUPPLIER_ISSUED_BY', null),

        // Voliteľné logo (cesta v public/ alebo absolútna). PNG/JPG.
        'logo' => env('INVOICE_SUPPLIER_LOGO', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | DPH
    |--------------------------------------------------------------------------
    |
    | Sadzby platné v SR od 1. 1. 2025. `rates` sa ponúkajú v UI,
    | `standard` je predvolená sadzba pre nové položky.
    |
    */

    'vat' => [
        'standard' => (float) env('INVOICE_VAT_RATE', 23),
        'rates' => [23, 19, 5, 0],
        'home_country' => 'SK',

        // Krajiny EÚ – rozhoduje o reverse charge vs. vývoz mimo EÚ.
        'eu_countries' => [
            'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR',
            'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK',
            'SI', 'ES', 'SE',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Predvolené hodnoty dokladu
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        'currency' => 'EUR',
        'due_days' => (int) env('INVOICE_DUE_DAYS', 14),
        'payment_method' => 'transfer',

        // 0308 = bezhotovostná platba, najbežnejší konštantný symbol na SK.
        'constant_symbol' => env('INVOICE_CONSTANT_SYMBOL', '0308'),

        'unit' => 'mesiac',
        'locale' => 'sk',

        // Text pod položkami – doplní sa do každej novej faktúry.
        'footer_note' => env('INVOICE_FOOTER_NOTE', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | QR platba
    |--------------------------------------------------------------------------
    |
    | pay_by_square – slovenský štandard, číta ho každá SK banková appka.
    | epc           – európsky štandard (EPC069-12), používajú ho AT/DE banky.
    |
    */

    'qr' => [
        'enabled' => filter_var(env('INVOICE_QR_ENABLED', true), FILTER_VALIDATE_BOOL),
        'format' => env('INVOICE_QR_FORMAT', 'pay_by_square'), // pay_by_square|epc
        'size' => 320,
    ],

    /*
    |--------------------------------------------------------------------------
    | Upomienky
    |--------------------------------------------------------------------------
    |
    | Kľúč = počet dní po splatnosti, hodnota = tón správy. Príkaz
    | `php artisan invoices:remind` posiela vždy najviac jednu upomienku denne
    | a nikdy nie tú istú úroveň dvakrát.
    |
    */

    'reminders' => [
        'enabled' => filter_var(env('INVOICE_REMINDERS_ENABLED', true), FILTER_VALIDATE_BOOL),
        'schedule' => [
            3 => 'friendly',   // "možno vám unikla"
            10 => 'firm',      // "prosíme o úhradu"
            21 => 'final',     // "posledná výzva pred pozastavením"
        ],
        'max' => 3,
        'copy_to_supplier' => filter_var(env('INVOICE_REMINDERS_COPY', false), FILTER_VALIDATE_BOOL),
    ],

    /*
    |--------------------------------------------------------------------------
    | Automatická fakturácia predplatného
    |--------------------------------------------------------------------------
    */

    'billing_run' => [
        // Koľko dní pred koncom obdobia vystaviť faktúru na ďalšie obdobie.
        'issue_days_before' => (int) env('INVOICE_ISSUE_DAYS_BEFORE', 7),
        // Vystavovať automaticky ako koncept (bezpečnejšie) alebo rovno vystaviť?
        'auto_issue' => filter_var(env('INVOICE_AUTO_ISSUE', false), FILTER_VALIDATE_BOOL),
        'auto_send' => filter_var(env('INVOICE_AUTO_SEND', false), FILTER_VALIDATE_BOOL),
        'skip_zero' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Úložisko PDF
    |--------------------------------------------------------------------------
    */

    'storage' => [
        'disk' => env('INVOICE_DISK', 'local'),
        'path' => 'invoices',
    ],

];
