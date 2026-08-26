<?php

return [

    'edit' => 'Úprava tokenu',
    'subtitle' => 'Popis a oprávnění. Samotný token změnit nelze – v databázi je z něj jen hash.',

    'name' => 'Popis',
    'name_hint' => 'Podle čeho token poznáš za půl roku – například „produkční server“.',
    'abilities' => 'Oprávnění',
    'abilities_hint' => 'Token projde jen na volání, která má povolená. Ostatní dostanou 403.',
    'abilities_required' => 'Token musí mít alespoň jedno oprávnění.',
    'prefix' => 'Prefix',
    'product' => 'Projekt',
    'last_used' => 'Naposledy použit',
    'never' => 'nikdy',
    'created' => 'Vytvořen',
    'select_all' => 'Označit všechny',
    'deselect_all' => 'Odznačit všechny',
    'save' => 'Uložit změny',
    'saving' => 'Ukládám…',
    'back' => 'Zpět na přehled',
    'saved' => 'Token byl upraven.',
    'revoked' => 'Token byl zrušen.',
    'unrevoked' => 'Token je znovu povolen – projekt přes něj opět projde.',

    'issued' => 'Nový service token',
    'issued_hint' => 'Ulož si ho do konfigurace projektu. V databázi z něj zůstává jen hash, takže znovu ho ukázat neumíme – pokud ho ztratíš, vygeneruj nový.',
    'webhook_secret' => 'Podpisový klíč webhooku',
    'webhook_secret_hint' => 'Klíčem ověřuješ hlavičku X-Accounts-Signature. Ulož si ho, podruhé se nezobrazí.',

    'state' => [
        'revoked' => 'Token je zrušený – projekt přes něj na API neprojde. Přes „Znovu povolit token“ se dá vrátit do hry, hodnota tokenu zůstává stejná.',
        'trashed' => 'Token je v koši. Lze jej obnovit nebo odstranit natrvalo.',
    ],

    'ability' => [
        'organizations:read' => [
            'label' => 'Čtení firem',
            'description' => 'Seznam a detail organizací projektu, vyhledání podle IČO.',
        ],
        'organizations:write' => [
            'label' => 'Zápis firem',
            'description' => 'Založení a úprava organizace z projektu.',
        ],
        'entitlements:read' => [
            'label' => 'Čtení limitů',
            'description' => 'Plán, funkce a limity firmy – podle toho se projekt rozhoduje, co pustí.',
        ],
        'usage:write' => [
            'label' => 'Hlášení spotřeby',
            'description' => 'Zápis naměřené spotřeby k metrikám z katalogu.',
        ],
    ],
];
