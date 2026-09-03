<?php

return [

    // Popisky oprávnení service tokenu. Kľúče sú hodnoty zo
    // ServiceClient::ABILITIES, tie isté, aké kontroluje routes/api.php.

    'edit' => 'Úprava tokenu',
    'subtitle' => 'Popis a oprávnenia. Samotný token sa zmeniť nedá – v databáze je z neho len hash.',

    'name' => 'Popis',
    'name_hint' => 'Podľa čoho token spoznáš o pol roka – napríklad „produkčný server“.',
    'abilities' => 'Oprávnenia',
    'abilities_hint' => 'Token prejde len na tie volania, ktoré má povolené. Ostatné dostanú 403.',
    'abilities_required' => 'Token musí mať aspoň jedno oprávnenie.',
    'prefix' => 'Prefix',
    'product' => 'Projekt',
    'last_used' => 'Naposledy použitý',
    'never' => 'nikdy',
    'created' => 'Vytvorený',
    'select_all' => 'Označiť všetky',
    'deselect_all' => 'Odznačiť všetky',
    'save' => 'Uložiť zmeny',
    'saving' => 'Ukladám…',
    'back' => 'Späť na prehľad',
    'saved' => 'Token bol upravený.',
    'revoked' => 'Token bol zrušený.',
    'unrevoked' => 'Token je znovu povolený – projekt cez neho opäť prejde.',

    'issued' => 'Nový service token',
    'issued_hint' => 'Ulož si ho do konfigurácie projektu. V databáze z neho zostáva len hash, takže znovu ho ukázať nevieme – ak ho stratíš, vygeneruj nový.',
    'webhook_secret' => 'Podpisový kľúč webhooku',
    'webhook_secret_hint' => 'Kľúčom overuješ hlavičku X-Accounts-Signature. Ulož si ho, druhýkrát sa nezobrazí.',

    'state' => [
        'revoked' => 'Token je zrušený – projekt cez neho na API neprejde. Cez „Znovu povoliť token“ sa dá vrátiť do hry, hodnota tokenu zostáva rovnaká.',
    ],

    'ability' => [
        'organizations:read' => [
            'label' => 'Čítanie firiem',
            'description' => 'Zoznam a detail organizácií projektu, vyhľadanie podľa IČO.',
        ],
        'organizations:write' => [
            'label' => 'Zápis firiem',
            'description' => 'Založenie a úprava organizácie z projektu.',
        ],
        'entitlements:read' => [
            'label' => 'Čítanie limitov',
            'description' => 'Plán, funkcie a limity firmy – podľa toho sa projekt rozhoduje, čo pustí.',
        ],
        'usage:write' => [
            'label' => 'Hlásenie spotreby',
            'description' => 'Zápis nameranej spotreby k metrikám z katalógu.',
        ],
    ],
];
