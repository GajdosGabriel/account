<?php

return [

    'subscription_status' => [
        'trialing' => 'Testphase',
        'active' => 'Aktiv',
        'past_due' => 'Überfällig',
        'suspended' => 'Gesperrt',
        'cancelled' => 'Gekündigt',
        'none' => 'Kein Abonnement',
    ],

    'invoice_status' => [
        'draft' => 'Entwurf',
        'issued' => 'Ausgestellt',
        'sent' => 'Versendet',
        'partially_paid' => 'Teilweise bezahlt',
        'paid' => 'Bezahlt',
        'overdue' => 'Überfällig',
        'cancelled' => 'Storniert',
    ],

    'invoice_type' => [
        'invoice' => 'Rechnung – Steuerbeleg',
        'proforma' => 'Proformarechnung',
        'credit_note' => 'Gutschrift – korrigierter Steuerbeleg',
    ],

    'invoice_type_short' => [
        'invoice' => 'Rechnung',
        'proforma' => 'Proforma',
        'credit_note' => 'Gutschrift',
    ],

    'legal_form' => [
        'sro' => 'Gesellschaft mit beschränkter Haftung',
        'zivnost' => 'Gewerbe',
        'as' => 'Aktiengesellschaft',
        'ks' => 'Kommanditgesellschaft',
        'vos' => 'Offene Handelsgesellschaft',
        'druzstvo' => 'Genossenschaft',
        'nezisk' => 'Gemeinnützige Organisation',
        'fyzicka' => 'Natürliche Person',
        'ine' => 'Sonstige',
    ],

    'subject_type' => [
        'company' => [
            'label' => 'Unternehmen',
            'description' => 'Firma, Gewerbetreibender oder gemeinnützige Organisation mit Unternehmensnummer.',
            'name_label' => 'Firmenname',
        ],
        'person' => [
            'label' => 'Privatperson',
            'description' => 'Privatperson ohne Unternehmensnummer. Name und Adresse genügen.',
            'name_label' => 'Vor- und Nachname',
        ],
    ],

    'vat_mode' => [
        'non_payer' => [
            'label' => 'Kein USt-Pflichtiger',
            'description' => 'Auf der Rechnung wird keine USt. ausgewiesen.',
        ],
        'payer' => [
            'label' => 'USt-pflichtig (§ 4)',
            'description' => 'Regulär USt-pflichtig, Rechnungen mit USt.',
        ],
        'reg_7' => [
            'label' => 'Registriert nach § 7',
            'description' => 'Warenerwerb aus der EU über dem Schwellenwert. Nicht USt-pflichtig, hat aber eine USt-IdNr.',
        ],
        'reg_7a' => [
            'label' => 'Registriert nach § 7a',
            'description' => 'Bezug oder Erbringung von Leistungen aus der/in die EU. Nicht USt-pflichtig, hat aber eine USt-IdNr.',
        ],
    ],

    'organization_status' => [
        'active' => 'Aktiv',
        'suspended' => 'Gesperrt',
        'archived' => 'Archiviert',
        'deleted' => 'Gelöscht',
    ],
];
