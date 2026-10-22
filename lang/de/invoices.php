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

    // Rechnungseinstellungen (/invoices/settings).
    'settings' => [
        'link' => 'Einstellungen',
        'title' => 'Rechnungseinstellungen',
        'subtitle' => 'Mahnungen, Versand, Standardwerte und automatische Abrechnung',
        'back' => 'Rechnungen',
        'save' => 'Einstellungen speichern',
        'saved' => 'Einstellungen wurden gespeichert.',
        'unchanged' => 'Nichts geändert.',
        'reset' => 'Standard wiederherstellen',
        'reset_confirm' => 'Alle Rechnungseinstellungen auf die Konfigurationswerte (.env) zurücksetzen?',
        'reset_done' => 'Einstellungen wurden zurückgesetzt.',
        'unsaved' => 'Du hast ungespeicherte Änderungen.',
        'default_hint' => 'Standard: :value',
        'on' => 'an',
        'off' => 'aus',
        'none' => 'leer',

        'reminders' => [
            'title' => 'Mahnungen',
            'description' => 'Automatische Erinnerungen an überfällige Rechnungen.',
            'enabled' => 'Mahnungen automatisch senden',
            'enabled_hint' => 'Versand werktags um 9:30, höchstens eine pro Tag und jede Stufe nur einmal. Manuell jederzeit aus dem Rechnungsdetail möglich.',
            'disabled_note' => 'Automatische Mahnungen sind aus – überfällige Rechnungen werden nur markiert.',
            'overdue_now' => '{0} Derzeit ist kein Beleg überfällig.|{1} Derzeit ist :count Beleg überfällig.|[2,*] Derzeit sind :count Belege überfällig.',
            'levels' => 'Stufen und Zeitpunkt',
            'levels_hint' => 'Eine strengere Stufe muss später kommen als eine mildere. Üblich: 3 – 10 – 21 Tage.',
            'days_after' => 'Tage nach Fälligkeit',
            'tones' => [
                'friendly' => 'Freundliche Erinnerung',
                'firm' => 'Mahnung',
                'final' => 'Letzte Mahnung',
            ],
            'tone_hints' => [
                'friendly' => '„Vielleicht ist es Ihnen entgangen…“ – höflicher Hinweis.',
                'firm' => 'Nachdrückliche Zahlungsaufforderung.',
                'final' => 'Kündigt die Sperrung des Dienstes nach Fristablauf an.',
            ],
            'min_amount' => 'Restbeträge ignorieren unter',
            'min_amount_hint' => 'Z. B. Rundung oder Bankgebühren. 0 = alles mahnen.',
            'final_grace_days' => 'Frist in der letzten Mahnung',
            'final_grace_days_hint' => '„Geht die Zahlung nicht innerhalb von X Tagen ein, sperren wir den Dienst.“',
            'days' => 'Tage',
            'attach_pdf' => 'Rechnungs-PDF anhängen',
            'copy_to_supplier' => 'Blindkopie an unsere E-Mail senden',
            'copy_to_supplier_hint' => 'Kopie geht an :email.',
            'no_supplier_email' => 'Lieferant hat keine E-Mail – es wird keine Kopie gesendet.',
        ],

        'sending' => [
            'title' => 'Versand von Belegen',
            'description' => 'Wer Rechnungen erhält und wie.',
            'require_verified' => 'Nur an bestätigte Rechnungs-E-Mail senden',
            'require_verified_hint' => 'Schützt vor Tippfehlern. Firmen ohne bestätigte E-Mail erhalten die Rechnung nicht.',
            'bcc' => 'Blindkopie gesendeter Rechnungen',
            'bcc_hint' => 'Z. B. Postfach des Buchhalters oder Archiv. Leer = keine.',
            'mode' => 'Versandmodus',
            'mode_hint' => 'Gilt für manuellen Versand, automatischen Versand bei der Abo-Abrechnung und Mahnungen. Eine einzelne Firma stoppen Sie in ihren Daten: Rechnungszustellung → Keine Belege senden.',
            'modes' => [
                'live' => 'Live',
                'redirect' => 'Test – alles an meine Adresse',
                'off' => 'Aus',
            ],
            'mode_hints' => [
                'live' => 'Belege und Mahnungen gehen an Kunden.',
                'redirect' => 'Kunden erhalten nichts. Die E-Mail kommt mit [TEST] markiert an Sie, der Belegstatus ändert sich nicht.',
                'off' => 'Nichts wird versendet. Rechnungen können weiter ausgestellt werden.',
            ],
            'redirect_to' => 'Testadresse',
            'redirect_to_hint' => 'Alle Belege und Mahnungen gehen hierher statt an Kunden.',
            'off_note' => 'Versand ist aus – die Schaltflächen „Senden“ und „Mahnung senden“ werden ausgeblendet, automatischer Versand wird übersprungen.',
        ],

        'defaults' => [
            'title' => 'Standardwerte für Belege',
            'description' => 'Werden in jeden neuen Entwurf übernommen und können dort geändert werden.',
            'due_days' => 'Zahlungsziel',
            'due_days_hint' => 'Gilt für Firmen ohne eigenes Zahlungsziel. Üblich 14 Tage.',
            'constant_symbol' => 'Konstantsymbol',
            'constant_symbol_hint' => '0308 = bargeldlose Zahlung für Dienstleistungen.',
            'unit' => 'Einheit',
            'vat_rate' => 'Standard-USt-Satz',
            'footer_note' => 'Hinweis unter den Positionen',
            'footer_note_hint' => 'Z. B. „Vielen Dank für Ihren Auftrag.“ oder ein Hinweis auf Verzugszinsen.',
        ],

        'qr' => [
            'title' => 'QR-Zahlung',
            'description' => 'QR-Code auf der Rechnung und in E-Mails – der Kunde scannt ihn in der Banking-App.',
            'enabled' => 'QR-Code anzeigen',
            'format' => 'Format',
            'formats' => [
                'pay_by_square' => 'PAY by square (slowakische Banken)',
                'epc' => 'EPC / SEPA (AT, DE und weitere EU-Banken)',
            ],
        ],

        'billing_run' => [
            'title' => 'Automatische Abo-Abrechnung',
            'description' => 'Jeden Morgen um 6:00 entstehen Belege für Abos, deren Zeitraum endet.',
            'issue_days_before' => 'Im Voraus abrechnen',
            'issue_days_before_hint' => 'Wie viele Tage vor Periodenende die nächste Rechnung erstellt wird.',
            'auto_issue' => 'Sofort ausstellen (Nummer vergeben)',
            'auto_issue_hint' => 'Aus = Entwürfe zur Prüfung.',
            'auto_send' => 'Ausgestellte Rechnungen sofort an Kunden senden',
            'auto_send_hint' => 'Schaltet auch das Ausstellen ein – Entwürfe werden nie versendet.',
            'skip_zero' => 'Kostenlose Tarife nicht abrechnen',
        ],

        'errors' => [
            'no_level' => 'Mindestens eine Mahnstufe aktivieren oder Mahnungen ausschalten.',
            'level_order' => 'Jede weitere Stufe muss später kommen als die vorherige.',
        ],
    ],
];
