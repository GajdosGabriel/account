<?php

return [

    'title' => 'Übersicht',

    'summary' => [
        'mrr' => 'Monatlich wiederkehrender Umsatz',
        'organizations' => 'Unternehmen',
        'active' => 'aktive Abonnements',
        'trialing' => 'in der Testphase',
        'payment_issues' => 'mit Zahlungsproblem',
    ],

    'invoicing' => [
        'heading' => 'Rechnungsstellung',
        'all' => 'Alle Belege →',
        'invoiced_month' => 'Diesen Monat fakturiert',
        'paid_month' => 'Diesen Monat bezahlt',
        'outstanding' => 'Offen gesamt',
        'overdue' => 'Überfällig',
        'documents' => '{1} :count Beleg|[0,*] :count Belege',
    ],

    'forecast' => [
        'title' => 'Umsatzprognose',
        'description' => 'Was in :days Tagen eingehen soll, also bis :until.',
        'note' => 'Nichts wird geschätzt – es sind fällige Forderungen und Verlängerungen, die bereits erfasst sind.',
        'due' => 'Fällige Rechnungen',
        'renewals' => 'Abo-Verlängerungen',
        'at_risk' => 'Gefährdet, überfällig',
        'avg_days_to_pay' => 'Durchschnittliche Zahlungsdauer:',
        'days' => '{1} :count Tag|[0,*] :count Tage',
        'drafts' => 'Entwürfe warten auf Ausstellung:',
    ],

    'history' => [
        'title' => 'Entwicklung der Rechnungsstellung',
        'description' => 'Die letzten sechs Monate: ausgestellt und davon bezahlt.',
        'invoiced' => 'ausgestellt',
        'paid' => 'bezahlt',
        'bar_invoiced' => 'Ausgestellt: :amount',
        'bar_paid' => 'Bezahlt: :amount',
        'empty' => 'Noch nichts zu zeichnen – stelle den ersten Beleg aus.',
    ],

    'products' => [
        'heading' => 'Verbundene Projekte',
        'active' => 'aktiv',
        'inactive' => 'deaktiviert',
        'organizations' => '{1} Unternehmen|[0,*] Unternehmen',
        'empty' => 'Erstes Projekt hinzufügen',
    ],

    'attention' => [
        'title' => 'Erfordert Aufmerksamkeit',
        'description' => 'Überfällige oder gesperrte Abonnements.',
        'deadline' => 'bis :date',
        'empty' => 'Alles ist bezahlt.',
    ],

    'near_limit' => [
        'title' => 'Nahe am Limit',
        'description' => 'Kandidaten für einen höheren Tarif.',
        'empty' => 'Noch nähert sich niemand einem Limit.',
    ],
];
