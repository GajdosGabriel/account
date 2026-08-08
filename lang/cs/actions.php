<?php

return [

    'menu' => 'Akce',
    'cancel' => 'Zrušit',
    'show_password' => 'Zobrazit heslo',
    'hide_password' => 'Skrýt heslo',
    'empty' => 'Žádné dostupné akce',
    'disabled' => 'Tato akce zde není povolena',
    'trashed' => 'v koši',

    'view' => 'Zobrazit',
    'edit' => 'Upravit',
    'delete' => 'Smazat',
    'restore' => 'Obnovit z koše',
    'force_delete' => 'Odstranit natrvalo',

    'confirm' => [
        'delete' => 'Přesunout „:name“ do koše? Lze vrátit zpět.',
        'restore' => 'Vrátit „:name“ z koše?',
        'force_delete' => 'Natrvalo odstranit „:name“? Tuto akci nelze vzít zpět.',
    ],

    'flash' => [
        'deleted' => 'Přesunuto do koše.',
        'restored' => 'Obnoveno z koše.',
        'force_deleted' => 'Natrvalo odstraněno.',
    ],

    'invoice' => [
        'view' => 'Otevřít detail',
        'preview' => 'Náhled faktury',
        'download' => 'Stáhnout PDF',
        'issue' => 'Vystavit doklad',
        'send' => 'Poslat e-mailem',
        'resend' => 'Poslat znovu e-mailem',
        'remind' => 'Poslat upomínku',
        'pay' => 'Zaznamenat úhradu',
        'edit' => 'Upravit koncept',
        'duplicate' => 'Vytvořit kopii',
        'convert' => 'Vystavit fakturu ze zálohy',
        'credit' => 'Vystavit dobropis',
        'cancel' => 'Stornovat',
        'delete' => 'Smazat koncept',
        'restore' => 'Obnovit z koše',
        'force_delete' => 'Smazat natrvalo',
        'disabled' => 'Tato akce není pro tento doklad povolena',
        'days' => ':count dní',

        'confirm' => [
            'issue' => 'Vystavením se doklad zamkne a dostane číslo. Pokračovat?',
            'remind' => 'Odeslat zákazníkovi upomínku?',
            'cancel' => 'Opravdu stornovat tento doklad?',
            'delete' => 'Přesunout koncept do koše? Lze vrátit zpět.',
            'force_delete' => 'Nenávratné smazání. Opravdu pokračovat?',
        ],
    ],

    'token' => [
        'revoke' => 'Zrušit token',
        'unrevoke' => 'Znovu povolit token',
        'confirm' => [
            'revoke' => 'Zrušit token „:name“? Projekt okamžitě ztratí přístup k API.',
            'unrevoke' => 'Znovu povolit token „:name“? Projekt přes něj opět projde na API, se stejnou hodnotou jako dřív.',
        ],
    ],
];
