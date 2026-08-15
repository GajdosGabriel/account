<?php

return [

    // Prehľad pre prevádzkovateľa. Kľúče kopírujú poradie sekcií na stránke.

    'title' => 'Prehľad',

    'summary' => [
        'mrr' => 'Mesačný opakovaný príjem',
        'organizations' => 'organizácií',
        'active' => 'aktívnych predplatných',
        'trialing' => 'v skúšobnom období',
        'payment_issues' => 's problémom platby',
    ],

    'invoicing' => [
        'heading' => 'Fakturácia',
        'all' => 'Všetky doklady →',
        'invoiced_month' => 'Vyfakturované tento mesiac',
        'paid_month' => 'Uhradené tento mesiac',
        'outstanding' => 'Neuhradené spolu',
        'overdue' => 'Po splatnosti',
        // Číslo si nesie samotný text, aby sa dalo skloňovať s ním.
        'documents' => '{1} :count doklad|[2,4] :count doklady|[0,*] :count dokladov',
    ],

    'forecast' => [
        'title' => 'Prognóza príjmu',
        'description' => 'Čo má pritiecť do :days dní, teda do :until.',
        'note' => 'Nič sa neodhaduje – sú to splatné pohľadávky a obnovy, ktoré už v evidencii sú.',
        'due' => 'Splatné faktúry',
        'renewals' => 'Obnovy predplatných',
        'at_risk' => 'Ohrozené po splatnosti',
        'avg_days_to_pay' => 'Priemerná doba úhrady:',
        'days' => '{1} :count deň|[2,4] :count dni|[0,*] :count dní',
        'drafts' => 'Konceptov čaká na vystavenie:',
    ],

    'history' => [
        'title' => 'Vývoj fakturácie',
        'description' => 'Posledných šesť mesiacov: vystavené a z toho uhradené.',
        'invoiced' => 'vystavené',
        'paid' => 'uhradené',
        'bar_invoiced' => 'Vystavené: :amount',
        'bar_paid' => 'Uhradené: :amount',
        'empty' => 'Zatiaľ nie je čo kresliť – vystav prvý doklad.',
    ],

    'products' => [
        'heading' => 'Pripojené projekty',
        'active' => 'aktívny',
        'inactive' => 'vypnutý',
        'organizations' => '{1} organizácia|[2,4] organizácie|[0,*] organizácií',
        'empty' => 'Pridať prvý projekt',
    ],

    'attention' => [
        'title' => 'Vyžaduje pozornosť',
        'description' => 'Predplatné po splatnosti alebo pozastavené.',
        'deadline' => 'do :date',
        'empty' => 'Všetko je uhradené.',
    ],

    'near_limit' => [
        'title' => 'Blízko limitu',
        'description' => 'Kandidáti na vyšší plán.',
        'empty' => 'Nikto sa zatiaľ nepribližuje k limitu.',
    ],
];
