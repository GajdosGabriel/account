<?php

return [

    // Zoznam dokladov v back-office.

    'title' => 'Faktúry',
    'subtitle' => 'Doklady, pohľadávky a exporty pre účtovníka',
    'create' => 'Nový doklad',
    'records' => 'dokladov',
    'documents' => '{1} :count doklad|[2,4] :count doklady|[0,*] :count dokladov',

    'export' => [
        'label' => 'Export',
        'csv' => 'CSV pre Excel',
        'xml' => 'XML pre účtovníctvo',
    ],

    // Pás nad zoznamom – kliknutím sa filtruje.
    'stats' => [
        'drafts' => 'Koncepty',
        'drafts_hint' => 'čakajú na vystavenie',
        'unpaid' => 'Neuhradené',
        'overdue' => 'Po splatnosti',
        'paid_month' => 'Uhradené tento mesiac',
        'paid_month_hint' => 'prijaté platby',
    ],

    'filter' => [
        'search' => 'Hľadať',
        'search_placeholder' => 'Číslo, VS, firma alebo IČO…',
        'all_statuses' => 'Všetky stavy',
        'overdue' => 'Po splatnosti',
        'trashed' => 'Kôš (:count)',
        'all_types' => 'Všetky typy',
        'from' => 'Vystavené od',
        'to' => 'Vystavené do',
        'found' => 'Nájdených: :count',
    ],

    'table' => [
        'document' => 'Doklad',
        'customer' => 'Odberateľ',
        'issued' => 'Vystavené',
        'due' => 'Splatnosť',
        'total' => 'Suma',
        'status' => 'Stav',
        'days_overdue' => '{1} :count deň po splatnosti|[2,4] :count dni po splatnosti|[0,*] :count dní po splatnosti',
        'outstanding' => 'zostáva :amount',
    ],

    'empty' => [
        'title' => 'Žiadne doklady',
        'filtered' => 'Skús uvoľniť filtre.',
        'none' => 'Vystav prvú faktúru alebo spusti automatickú fakturáciu.',
    ],
];
