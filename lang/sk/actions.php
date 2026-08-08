<?php

return [

    // Popisky akcií v kontextovom menu. Zdieľajú sa do frontendu cez
    // HandleInertiaRequests, takže rovnaký text vidí server aj Vue.

    'menu' => 'Akcie',
    'cancel' => 'Zrušiť',
    'show_password' => 'Zobraziť heslo',
    'hide_password' => 'Skryť heslo',
    'empty' => 'Žiadne dostupné akcie',
    'disabled' => 'Táto akcia tu nie je povolená',
    'trashed' => 'v koši',

    'view' => 'Zobraziť',
    'edit' => 'Upraviť',
    'delete' => 'Vymazať',
    'restore' => 'Obnoviť z koša',
    'force_delete' => 'Odstrániť natrvalo',

    'confirm' => [
        'delete' => 'Presunúť „:name“ do koša? Dá sa vrátiť späť.',
        'restore' => 'Vrátiť „:name“ z koša?',
        'force_delete' => 'Natrvalo odstrániť „:name“? Táto akcia sa nedá vrátiť späť.',
    ],

    'flash' => [
        'deleted' => 'Presunuté do koša.',
        'restored' => 'Obnovené z koša.',
        'force_deleted' => 'Natrvalo odstránené.',
    ],

    // Doklad má vlastné akcie – pravidlá k nim sú v InvoicePolicy.
    'invoice' => [
        'view' => 'Otvoriť detail',
        'preview' => 'Náhľad faktúry',
        'download' => 'Stiahnuť PDF',
        'issue' => 'Vystaviť doklad',
        'send' => 'Poslať e-mailom',
        'resend' => 'Poslať znovu e-mailom',
        'remind' => 'Poslať upomienku',
        'pay' => 'Zaznamenať úhradu',
        'edit' => 'Upraviť koncept',
        'duplicate' => 'Vytvoriť kópiu',
        'convert' => 'Vystaviť faktúru zo zálohy',
        'credit' => 'Vystaviť dobropis',
        'cancel' => 'Stornovať',
        'delete' => 'Zmazať koncept',
        'restore' => 'Obnoviť z koša',
        'force_delete' => 'Zmazať natrvalo',
        'disabled' => 'Táto akcia nie je pre tento doklad povolená',
        'days' => ':count dní',

        'confirm' => [
            'issue' => 'Vystavením sa doklad zamkne a dostane číslo. Pokračovať?',
            'remind' => 'Odoslať zákazníkovi upomienku?',
            'cancel' => 'Naozaj stornovať tento doklad?',
            'delete' => 'Presunúť koncept do koša? Dá sa vrátiť späť.',
            'force_delete' => 'Nenávratné zmazanie. Naozaj pokračovať?',
        ],
    ],

    // Token sa okrem mazania dá aj zrušiť – zostáva v evidencii,
    // ale projekt ním už neprejde cez autentifikáciu.
    'token' => [
        'revoke' => 'Zrušiť token',
        // Zámerne nie „obnoviť“ – to je návrat z koša, toto je iné.
        'unrevoke' => 'Znovu povoliť token',
        'confirm' => [
            'revoke' => 'Zrušiť token „:name“? Projekt okamžite stratí prístup k API.',
            'unrevoke' => 'Znovu povoliť token „:name“? Projekt cez neho opäť prejde na API, s tou istou hodnotou ako predtým.',
        ],
    ],
];
