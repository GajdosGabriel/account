<?php

/*
|--------------------------------------------------------------------------
| Napojenie na Account
|--------------------------------------------------------------------------
| Skopíruj do config/account.php v projekte a doplň .env:
|
|   ACCOUNT_URL=https://account.tvojafirma.sk
|   ACCOUNT_TOKEN=acc_xxxxxxxxxxxxxxxx
|
*/

return [
    'url' => rtrim((string) env('ACCOUNT_URL', 'http://account.local'), '/'),
    'token' => env('ACCOUNT_TOKEN'),
    'webhook_secret' => env('ACCOUNT_WEBHOOK_SECRET'),

    // Timeout schválne krátky – Account nesmie brzdiť tvoju aplikáciu.
    'timeout' => 4,

    'cache' => [
        // Bežná platnosť entitlements
        'ttl' => 300,
        // Ako dlho smieme použiť poslednú známu hodnotu, keď Account nebeží.
        // Po uplynutí prepneme na read-only, aby výpadok neznamenal
        // neobmedzený prístup zadarmo.
        'stale_ttl' => 7 * 24 * 3600,
        // Firemné údaje sa menia zriedka
        'organization_ttl' => 3600,
    ],
];
