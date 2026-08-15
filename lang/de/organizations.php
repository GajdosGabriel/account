<?php

return [

    'title' => 'Unternehmen',
    'subtitle' => 'Eine Quelle der Wahrheit für alle verbundenen Projekte.',
    'create' => 'Neues Unternehmen',
    'records' => 'Unternehmen',

    'table' => [
        'name' => 'Name',
        'ico' => 'Unternehmensnummer',
        'vat_number' => 'USt-IdNr.',
        'city' => 'Stadt',
        'status' => 'Status',
        'products' => 'Projekte',
        'verified' => 'geprüft',
    ],

    'empty' => [
        'trashed' => 'Im Papierkorb liegt kein Unternehmen.',
        'filtered' => 'Kein Unternehmen entspricht dem Filter.',
        'none' => 'Hier ist noch kein Unternehmen.',
    ],

    'filter' => [
        'search' => 'Suchen',
        'search_placeholder' => 'Nach Name oder Unternehmensnummer suchen…',
        'product' => 'Projekt',
        'all_products' => 'Alle Projekte',
        'status' => 'Status',
        'all_statuses' => 'Alle Status',
        'trashed' => 'Papierkorb (:count)',
        'linked' => 'Verknüpfung mit Projekt',
        'linked_any' => 'Mit und ohne Projekt',
        'linked_only' => 'Nur mit Projekt verknüpft',
        'linked_none' => 'Nur ohne Projekt',

        'statuses' => [
            'active' => 'Aktiv',
            'suspended' => 'Gesperrt',
            'archived' => 'Archiviert',
        ],
    ],

    'form' => [
        'create' => 'Neues Unternehmen',
        'edit' => 'Unternehmen bearbeiten',
        'intro' => 'Diese Daten nutzen alle verbundenen Projekte und die Rechnungsstellung.',

        'subject' => [
            'title' => 'Kundentyp',
            'description' => 'Von einer Privatperson werden keine Firmendaten verlangt – eine Unternehmensnummer wird sie nie haben.',
        ],

        'identification' => [
            'title' => 'Identifikation',
            'description' => 'Die Unternehmensnummer laden wir aus dem Register (SK: RPO, CZ: ARES), die USt-IdNr. prüfen wir in VIES.',
            'description_person' => 'Bei einer Privatperson genügt der Name. Auf die Rechnung kommt die Adresse unten.',
        ],

        'lookup' => [
            'fetch' => 'Laden',
            'verify' => 'Prüfen',
            'filled' => 'Daten aus dem Register :register übernommen.',
            'not_found' => 'Die Unternehmensnummer wurde nicht gefunden.',
            'registry_down' => 'Das Register ist nicht erreichbar.',
            'vat_valid' => 'In VIES gültig',
            'vat_invalid' => 'Die USt-IdNr. ist nicht gültig.',
            'vies_down' => 'VIES ist nicht erreichbar.',
        ],

        'existing' => [
            'intro' => 'Ein Unternehmen mit dieser Unternehmensnummer haben wir bereits —',
            'deleted' => '(gelöscht)',
            'archived' => '(archiviert)',
            'suspended' => '(gesperrt)',
            'filled' => 'Die Daten haben wir von dort übernommen.',
            'open' => 'Vorhandenes öffnen',
            'duplicate' => '— ein neues Speichern würde ein Duplikat anlegen.',
        ],

        'fields' => [
            'ico' => 'Unternehmensnummer',
            'name' => 'Anzeigename',
            'name_person' => 'Vor- und Nachname',
            'legal_name' => 'Firmenwortlaut',
            'legal_name_hint' => '— genau wie im Register',
            'legal_form' => 'Rechtsform',
            'dic' => 'Steuernummer',
            'vat_mode' => 'USt-Status',
            'ic_dph' => 'USt-IdNr.',
            'oss' => 'Für OSS registriert (Verkauf in die EU)',
            'register_court' => 'Registergericht / Behörde',
            'register_section' => 'Abteilung',
            'register_insert' => 'Einlage',
            'established_at' => 'Gründung',
            'street' => 'Straße',
            'street_no' => 'Nummer',
            'postal_code' => 'PLZ',
            'city' => 'Stadt',
            'country' => 'Land',
            'region' => 'Region',
            'email' => 'Allgemeine E-Mail',
            'billing_email' => 'Rechnungs-E-Mail',
            'phone' => 'Telefon',
            'website' => 'Web',
            'bank_name' => 'Bank',
            'iban' => 'IBAN',
            'swift' => 'SWIFT',
            'payment_terms_days' => 'Zahlungsziel (Tage)',
            'payment_method' => 'Zahlungsart',
            'currency' => 'Währung',
            'invoice_delivery' => 'Rechnungszustellung',
            'invoice_language' => 'Rechnungssprache',
            'supplier_number' => 'Unsere Nummer beim Kunden',
            'status' => 'Status des Unternehmens',
            'note' => 'Notiz',
        ],

        'placeholders' => [
            'legal_name' => 'Firma GmbH',
            'register_court' => 'Bezirksgericht Bratislava I',
            'street' => 'Hauptstraße',
            'region' => 'Region Bratislava',
            'bank_name' => 'Tatra banka',
        ],

        'register' => [
            'title' => 'Registereintrag',
            'description' => 'Pflichtangabe in der Rechnungsfußzeile und im Geschäftsverkehr.',
        ],

        'address' => [
            'title' => 'Firmensitz / Betriebsstätte',
            'title_person' => 'Adresse',
            'description' => 'Adresse wie im Gewerbeschein oder im Handelsregisterauszug.',
            'description_person' => 'Wohnsitzadresse – sie wird auf die Rechnung gedruckt.',
            'more' => 'Postanschrift, Lieferadressen und Betriebsstätten fügst du im Detail des Unternehmens hinzu.',
        ],

        'contact' => [
            'title' => 'Kontakt und Bankverbindung',
        ],

        'billing' => [
            'title' => 'Rechnungseinstellungen',
            'payment_methods' => [
                'transfer' => 'Überweisung',
                'card' => 'Karte',
                'cash' => 'Bar',
                'cod' => 'Nachnahme',
            ],
            'delivery' => [
                'email' => 'Per E-Mail',
                'post' => 'Per Post',
                'both' => 'Per E-Mail und Post',
            ],
            'languages' => [
                'sk' => 'Slowakisch',
                'en' => 'Englisch',
                'de' => 'Deutsch',
            ],
        ],

        'internal' => [
            'title' => 'Intern',
            'description' => 'Sieht nur der Betreiber, wird nicht an die Projekte gesendet.',
            'statuses' => [
                'active' => 'Aktiv',
                'suspended' => 'Gesperrt — Projekte sperren sie',
                'archived' => 'Archiviert',
            ],
        ],
    ],
];
