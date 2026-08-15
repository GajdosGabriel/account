<?php

return [

    'title' => 'Rechnungen',
    'subtitle' => 'Belege, Forderungen und Exporte für die Buchhaltung',
    'create' => 'Neuer Beleg',
    'records' => 'Belege',
    'documents' => '{1} :count Beleg|[0,*] :count Belege',

    'export' => [
        'label' => 'Export',
        'csv' => 'CSV für Excel',
        'xml' => 'XML für die Buchhaltung',
    ],

    'stats' => [
        'drafts' => 'Entwürfe',
        'drafts_hint' => 'warten auf Ausstellung',
        'unpaid' => 'Unbezahlt',
        'overdue' => 'Überfällig',
        'paid_month' => 'Diesen Monat bezahlt',
        'paid_month_hint' => 'eingegangene Zahlungen',
    ],

    'filter' => [
        'search' => 'Suchen',
        'search_placeholder' => 'Nummer, VS, Firma oder Unternehmensnummer…',
        'all_statuses' => 'Alle Status',
        'overdue' => 'Überfällig',
        'trashed' => 'Papierkorb (:count)',
        'all_types' => 'Alle Typen',
        'from' => 'Ausgestellt ab',
        'to' => 'Ausgestellt bis',
        'found' => 'Gefunden: :count',
    ],

    'table' => [
        'document' => 'Beleg',
        'customer' => 'Kunde',
        'issued' => 'Ausgestellt',
        'due' => 'Fällig',
        'total' => 'Betrag',
        'status' => 'Status',
        'days_overdue' => '{1} :count Tag überfällig|[0,*] :count Tage überfällig',
        'outstanding' => 'offen :amount',
    ],

    'empty' => [
        'title' => 'Keine Belege',
        'filtered' => 'Versuche, die Filter zu lockern.',
        'none' => 'Stelle die erste Rechnung aus oder starte die automatische Fakturierung.',
    ],
];
