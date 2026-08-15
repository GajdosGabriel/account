<?php

return [

    'title' => 'Organizace',
    'subtitle' => 'Jeden zdroj pravdy pro všechny připojené projekty.',
    'create' => 'Nová organizace',
    'records' => 'organizací',

    'table' => [
        'name' => 'Název',
        'ico' => 'IČO',
        'vat_number' => 'DIČ (DPH)',
        'city' => 'Město',
        'status' => 'Stav',
        'products' => 'Projektů',
        'verified' => 'ověřeno',
    ],

    'empty' => [
        'trashed' => 'V koši není žádná organizace.',
        'filtered' => 'Filtru neodpovídá žádná organizace.',
        'none' => 'Zatím tu není žádná organizace.',
    ],

    'filter' => [
        'search' => 'Hledat',
        'search_placeholder' => 'Hledat podle názvu nebo IČO…',
        'product' => 'Projekt',
        'all_products' => 'Všechny projekty',
        'status' => 'Stav',
        'all_statuses' => 'Všechny stavy',
        'trashed' => 'Koš (:count)',
        'linked' => 'Navázání na projekt',
        'linked_any' => 'Navázané i bez projektu',
        'linked_only' => 'Jen navázané na projekt',
        'linked_none' => 'Jen bez projektu',

        'statuses' => [
            'active' => 'Aktivní',
            'suspended' => 'Pozastavené',
            'archived' => 'Archivované',
        ],
    ],

    'form' => [
        'create' => 'Nová organizace',
        'edit' => 'Úprava organizace',
        'intro' => 'Tyto údaje používají všechny připojené projekty i fakturace.',

        'subject' => [
            'title' => 'Typ zákazníka',
            'description' => 'Soukromé osoby se na firemní údaje neptáme – IČO nikdy mít nebude.',
        ],

        'identification' => [
            'title' => 'Identifikace',
            'description' => 'IČO načteme z registru (SK: RPO, CZ: ARES), DIČ pro DPH ověříme ve VIES.',
            'description_person' => 'Soukromé osobě stačí jméno. Na fakturu se doplní adresa níže.',
        ],

        'lookup' => [
            'fetch' => 'Načíst',
            'verify' => 'Ověřit',
            'filled' => 'Údaje doplněny z registru :register.',
            'not_found' => 'IČO se nenašlo.',
            'registry_down' => 'Registr je nedostupný.',
            'vat_valid' => 'Platné ve VIES',
            'vat_invalid' => 'DIČ pro DPH není platné.',
            'vies_down' => 'VIES je nedostupný.',
        ],

        'existing' => [
            'intro' => 'Firmu s tímto IČO už máme v databázi —',
            'deleted' => '(smazaná)',
            'archived' => '(archivovaná)',
            'suspended' => '(pozastavená)',
            'filled' => 'Údaje jsme doplnili z ní.',
            'open' => 'Otevřít existující',
            'duplicate' => '— nové uložení by vytvořilo duplikát.',
        ],

        'fields' => [
            'ico' => 'IČO',
            'name' => 'Zobrazovaný název',
            'name_person' => 'Jméno a příjmení',
            'legal_name' => 'Obchodní firma',
            'legal_name_hint' => '— přesně jako v registru',
            'legal_form' => 'Právní forma',
            'dic' => 'DIČ',
            'vat_mode' => 'Vztah k DPH',
            'ic_dph' => 'DIČ (DPH)',
            'oss' => 'Registrovaná v OSS (prodej do EU)',
            'register_court' => 'Rejstříkový soud / úřad',
            'register_section' => 'Oddíl',
            'register_insert' => 'Vložka',
            'established_at' => 'Vznik',
            'street' => 'Ulice',
            'street_no' => 'Číslo',
            'postal_code' => 'PSČ',
            'city' => 'Město',
            'country' => 'Země',
            'region' => 'Kraj',
            'email' => 'Obecný e-mail',
            'billing_email' => 'E-mail na faktury',
            'phone' => 'Telefon',
            'website' => 'Web',
            'bank_name' => 'Banka',
            'iban' => 'IBAN',
            'swift' => 'SWIFT',
            'payment_terms_days' => 'Splatnost (dny)',
            'payment_method' => 'Způsob platby',
            'currency' => 'Měna',
            'invoice_delivery' => 'Doručování faktur',
            'invoice_language' => 'Jazyk faktury',
            'supplier_number' => 'Naše číslo u zákazníka',
            'status' => 'Stav organizace',
            'note' => 'Poznámka',
        ],

        'placeholders' => [
            'legal_name' => 'Firma, s. r. o.',
            'register_court' => 'Městský soud v Praze',
            'street' => 'Hlavní',
            'region' => 'Středočeský kraj',
            'bank_name' => 'Komerční banka',
        ],

        'register' => [
            'title' => 'Zápis v registru',
            'description' => 'Povinný údaj v patičce faktury i obchodní korespondence.',
        ],

        'address' => [
            'title' => 'Sídlo / místo podnikání',
            'title_person' => 'Adresa',
            'description' => 'Adresa jako na živnostenském listu nebo ve výpisu z obchodního rejstříku.',
            'description_person' => 'Adresa trvalého bydliště – tiskne se na fakturu.',
            'more' => 'Adresu pro zasílání pošty, dodací adresy a provozovny přidáš na detailu organizace.',
        ],

        'contact' => [
            'title' => 'Kontakt a bankovní spojení',
        ],

        'billing' => [
            'title' => 'Fakturační předvolby',
            'payment_methods' => [
                'transfer' => 'Převodem',
                'card' => 'Kartou',
                'cash' => 'Hotovost',
                'cod' => 'Dobírka',
            ],
            'delivery' => [
                'email' => 'E-mailem',
                'post' => 'Poštou',
                'both' => 'E-mailem i poštou',
            ],
            'languages' => [
                'sk' => 'Slovenština',
                'en' => 'Angličtina',
                'de' => 'Němčina',
            ],
        ],

        'internal' => [
            'title' => 'Interní',
            'description' => 'Vidí jen provozovatel, do projektů se neposílá.',
            'statuses' => [
                'active' => 'Aktivní',
                'suspended' => 'Pozastavená — projekty ji zamknou',
                'archived' => 'Archivovaná',
            ],
        ],
    ],
];
