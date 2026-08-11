<?php

return [

    'token' => [
        'missing' => 'Service-Token fehlt.',
        'invalid' => 'Ungültiges oder widerrufenes Service-Token.',
        'ability' => 'Das Token hat keine Berechtigung: :ability.',
        'product_inactive' => 'Das Projekt ist deaktiviert.',
        'no_product' => 'Das Token ist keinem Produkt zugeordnet.',
    ],

    'organization' => [
        'not_linked' => 'Das Unternehmen ist mit diesem Projekt nicht verknüpft.',
        'linked' => 'Das Unternehmen wurde mit dem Projekt verknüpft.',

        'billing_missing' => [
            'ico' => 'Unternehmensnummer',
            'address' => 'Adresse',
            'registered_address' => 'Firmensitz',
            'billing_email' => 'Rechnungs-E-Mail',
            'vat_number' => 'USt-IdNr.',
        ],
    ],

    'registry' => [
        'ico_not_found' => 'Die Unternehmensnummer wurde im Register nicht gefunden.',
        'ico_disabled' => 'Die Prüfung der Unternehmensnummer ist deaktiviert.',
        'rpo_unavailable' => 'Das Register ist derzeit nicht erreichbar.',
        'rpo_failed' => 'Das Register hat nicht geantwortet (:status).',
        'vies_disabled' => 'Die USt-IdNr.-Prüfung ist deaktiviert.',
        'vies_unavailable' => 'VIES ist derzeit nicht erreichbar.',
        'vies_failed' => 'VIES hat nicht geantwortet (:status).',
        'vat_prefix' => 'Die USt-IdNr. muss mit dem Ländercode beginnen, z. B. DE123456789.',
    ],

    'subscription' => [
        'no_organization' => 'Das Konto gehört noch zu keinem Unternehmen.',
        'organization_suspended' => 'Das Unternehmen ist gesperrt. Bitte wenden Sie sich an den Administrator.',
        'no_subscription' => 'Für dieses Projekt besteht kein Abonnement.',
        'past_due' => 'Die Rechnung ist überfällig. Der Zugriff bleibt bis :date bestehen.',
        'suspended' => 'Das Abonnement ist ausgesetzt – Daten können gelesen, aber nicht geändert werden.',
        'cancelled' => 'Das Abonnement wurde gekündigt.',
        'over_limit' => 'Das Tariflimit ist erreicht (:used von :limit). Neue Einträge sind nicht möglich.',
    ],
    'invoice' => [
        'locked' => 'Ein ausgestellter Beleg kann nicht mehr geändert werden. Erstelle eine Gutschrift.',
        'draft_only' => 'Diese Aktion ist nur für Entwürfe möglich.',
        'no_items' => 'Der Beleg enthält keine Positionen.',
        'missing_billing' => 'Dem Unternehmen fehlen Rechnungsdaten: :fields.',
        'no_email' => 'Das Unternehmen hat keine Rechnungs-E-Mail hinterlegt.',
        'already_paid' => 'Der Beleg ist bereits bezahlt.',
        'cancelled' => 'Ein stornierter Beleg kann nicht weiterverarbeitet werden.',
        'has_payment' => 'Für diesen Beleg ist eine Zahlung erfasst. Erstelle eine Gutschrift.',
        'no_series' => 'Für diese Belegart ist kein Nummernkreis angelegt.',
        'sent' => 'Der Beleg wurde an :email gesendet.',
        'reminder_sent' => 'Die Mahnung wurde versendet.',
        'not_overdue' => 'Der Beleg ist nicht überfällig.',
        'pdf_missing' => 'Für die PDF-Erstellung fehlt die dompdf-Bibliothek.',
    ],
];
