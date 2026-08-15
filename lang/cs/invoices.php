<?php

return [

    'title' => 'Faktury',
    'subtitle' => 'Doklady, pohledávky a exporty pro účetní',
    'create' => 'Nový doklad',
    'records' => 'dokladů',
    'documents' => '{1} :count doklad|[2,4] :count doklady|[0,*] :count dokladů',

    'export' => [
        'label' => 'Export',
        'csv' => 'CSV pro Excel',
        'xml' => 'XML pro účetnictví',
    ],

    'stats' => [
        'drafts' => 'Koncepty',
        'drafts_hint' => 'čekají na vystavení',
        'unpaid' => 'Neuhrazené',
        'overdue' => 'Po splatnosti',
        'paid_month' => 'Uhrazeno tento měsíc',
        'paid_month_hint' => 'přijaté platby',
    ],

    'filter' => [
        'search' => 'Hledat',
        'search_placeholder' => 'Číslo, VS, firma nebo IČO…',
        'all_statuses' => 'Všechny stavy',
        'overdue' => 'Po splatnosti',
        'trashed' => 'Koš (:count)',
        'all_types' => 'Všechny typy',
        'from' => 'Vystaveno od',
        'to' => 'Vystaveno do',
        'found' => 'Nalezeno: :count',
    ],

    'table' => [
        'document' => 'Doklad',
        'customer' => 'Odběratel',
        'issued' => 'Vystaveno',
        'due' => 'Splatnost',
        'total' => 'Částka',
        'status' => 'Stav',
        'days_overdue' => '{1} :count den po splatnosti|[2,4] :count dny po splatnosti|[0,*] :count dní po splatnosti',
        'outstanding' => 'zbývá :amount',
    ],

    'empty' => [
        'title' => 'Žádné doklady',
        'filtered' => 'Zkus uvolnit filtry.',
        'none' => 'Vystav první fakturu nebo spusť automatickou fakturaci.',
    ],
];
