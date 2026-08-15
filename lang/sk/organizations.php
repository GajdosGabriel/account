<?php

return [

    // Zoznam organizácií, filter nad ním a formulár na založenie či úpravu.

    'title' => 'Organizácie',
    'subtitle' => 'Jeden zdroj pravdy pre všetky pripojené projekty.',
    'create' => 'Nová organizácia',
    'records' => 'organizácií',

    'table' => [
        'name' => 'Názov',
        'ico' => 'IČO',
        'vat_number' => 'IČ DPH',
        'city' => 'Mesto',
        'status' => 'Stav',
        'products' => 'Projektov',
        'verified' => 'overené',
    ],

    'empty' => [
        'trashed' => 'V koši nie je žiadna organizácia.',
        'filtered' => 'Filtru nezodpovedá žiadna organizácia.',
        'none' => 'Zatiaľ tu nie je žiadna organizácia.',
    ],

    'filter' => [
        'search' => 'Hľadať',
        'search_placeholder' => 'Hľadať podľa názvu alebo IČO…',
        'product' => 'Projekt',
        'all_products' => 'Všetky projekty',
        'status' => 'Stav',
        'all_statuses' => 'Všetky stavy',
        'trashed' => 'Kôš (:count)',
        'linked' => 'Naviazanie na projekt',
        'linked_any' => 'Naviazané aj bez projektu',
        'linked_only' => 'Len naviazané na projekt',
        'linked_none' => 'Len bez projektu',

        // Voľby v zozname stavov sa vzťahujú na organizácie (množné číslo),
        // preto sa nedajú prevziať z enums.organization_status.
        'statuses' => [
            'active' => 'Aktívne',
            'suspended' => 'Pozastavené',
            'archived' => 'Archivované',
        ],
    ],

    'form' => [
        'create' => 'Nová organizácia',
        'edit' => 'Úprava organizácie',
        'intro' => 'Tieto údaje používajú všetky pripojené projekty aj fakturácia.',

        'subject' => [
            'title' => 'Typ zákazníka',
            'description' => 'Od súkromnej osoby sa firemné údaje nepýtajú – IČO nikdy mať nebude.',
        ],

        'identification' => [
            'title' => 'Identifikácia',
            'description' => 'IČO načítame z registra (SK: RPO, CZ: ARES), IČ DPH overíme vo VIES.',
            'description_person' => 'Súkromnej osobe stačí meno. Na faktúru sa doplní adresa nižšie.',
        ],

        'lookup' => [
            'fetch' => 'Načítať',
            'verify' => 'Overiť',
            'filled' => 'Údaje doplnené z registra :register.',
            'not_found' => 'IČO sa nenašlo.',
            'registry_down' => 'Register je nedostupný.',
            'vat_valid' => 'Platné vo VIES',
            'vat_invalid' => 'IČ DPH nie je platné.',
            'vies_down' => 'VIES je nedostupný.',
        ],

        // Duplicitné IČO – ponúkneme existujúcu firmu namiesto nového záznamu.
        'existing' => [
            'intro' => 'Firmu s týmto IČO už máme v databáze —',
            'deleted' => '(zmazaná)',
            'archived' => '(archivovaná)',
            'suspended' => '(pozastavená)',
            'filled' => 'Údaje sme doplnili z nej.',
            'open' => 'Otvoriť existujúcu',
            'duplicate' => '— nové uloženie by vytvorilo duplikát.',
        ],

        'fields' => [
            'ico' => 'IČO',
            'name' => 'Zobrazovaný názov',
            'name_person' => 'Meno a priezvisko',
            'legal_name' => 'Obchodné meno',
            'legal_name_hint' => '— presne ako v registri',
            'legal_form' => 'Právna forma',
            'dic' => 'DIČ',
            'vat_mode' => 'Vzťah k DPH',
            'ic_dph' => 'IČ DPH',
            'oss' => 'Registrovaná v OSS (predaj do EÚ)',
            'register_court' => 'Registrový súd / úrad',
            'register_section' => 'Oddiel',
            'register_insert' => 'Vložka',
            'established_at' => 'Vznik',
            'street' => 'Ulica',
            'street_no' => 'Číslo',
            'postal_code' => 'PSČ',
            'city' => 'Mesto',
            'country' => 'Krajina',
            'region' => 'Kraj',
            'email' => 'Všeobecný e-mail',
            'billing_email' => 'E-mail na faktúry',
            'phone' => 'Telefón',
            'website' => 'Web',
            'bank_name' => 'Banka',
            'iban' => 'IBAN',
            'swift' => 'SWIFT',
            'payment_terms_days' => 'Splatnosť (dni)',
            'payment_method' => 'Spôsob platby',
            'currency' => 'Mena',
            'invoice_delivery' => 'Doručovanie faktúr',
            'invoice_language' => 'Jazyk faktúry',
            'supplier_number' => 'Naše číslo u zákazníka',
            'status' => 'Stav organizácie',
            'note' => 'Poznámka',
        ],

        'placeholders' => [
            'legal_name' => 'Firma, s. r. o.',
            'register_court' => 'Okresný súd Bratislava I',
            'street' => 'Hlavná',
            'region' => 'Bratislavský kraj',
            'bank_name' => 'Tatra banka',
        ],

        'register' => [
            'title' => 'Zápis v registri',
            'description' => 'Povinný údaj v päticke faktúry aj obchodnej korešpondencie.',
        ],

        'address' => [
            'title' => 'Sídlo / miesto podnikania',
            'title_person' => 'Adresa',
            'description' => 'Adresa ako na živnostenskom liste alebo vo výpise z obchodného registra.',
            'description_person' => 'Adresa trvalého bydliska – tlačí sa na faktúru.',
            'more' => 'Adresu na zasielanie pošty, dodacie adresy a prevádzkarne pridáš na detaile organizácie.',
        ],

        'contact' => [
            'title' => 'Kontakt a bankové spojenie',
        ],

        'billing' => [
            'title' => 'Fakturačné preferencie',
            'payment_methods' => [
                'transfer' => 'Prevodom',
                'card' => 'Kartou',
                'cash' => 'Hotovosť',
                'cod' => 'Dobierka',
            ],
            'delivery' => [
                'email' => 'E-mailom',
                'post' => 'Poštou',
                'both' => 'E-mailom aj poštou',
            ],
            'languages' => [
                'sk' => 'Slovenčina',
                'en' => 'Angličtina',
                'de' => 'Nemčina',
            ],
        ],

        'internal' => [
            'title' => 'Interné',
            'description' => 'Vidí len prevádzkovateľ, do projektov sa neposiela.',
            'statuses' => [
                'active' => 'Aktívna',
                'suspended' => 'Pozastavená — projekty ju zamknú',
                'archived' => 'Archivovaná',
            ],
        ],
    ],
];
