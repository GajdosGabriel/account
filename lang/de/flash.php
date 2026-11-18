<?php

/*
|--------------------------------------------------------------------------
| Meldungen nach einer Aktion (Toast) – Deutsch
|--------------------------------------------------------------------------
*/

return [

    'saved' => 'Die Änderungen wurden gespeichert.',

    'auth' => [
        'failed' => 'Diese Zugangsdaten passen zu keinem Konto.',
        'reset_invalid' => 'Der Link zum Zurücksetzen des Passworts ist ungültig oder abgelaufen.',
        'reset_done' => 'Das Passwort wurde geändert, Sie können sich jetzt anmelden.',
        'reset_link_sent' => 'Falls ein Konto mit dieser E-Mail existiert, haben wir einen Link zum Zurücksetzen gesendet.',
        'password_changed' => 'Das Passwort wurde geändert. Andere Geräte wurden abgemeldet.',
        'cannot_remove_self' => 'Den eigenen Zugang kannst du nicht entfernen.',
        'cannot_remove_last' => 'Der letzte Operator kann nicht entfernt werden.',
    ],

    'organization' => [
        'created' => 'Die Organisation wurde angelegt.',
        'created_verify' => 'Die Organisation wurde angelegt. An die Rechnungs-E-Mail haben wir eine Bestätigungsanfrage gesendet.',
        'saved_verify' => 'Die Angaben wurden gespeichert. An die neue Rechnungs-E-Mail haben wir eine Bestätigungsanfrage gesendet.',
        'trashed' => 'Die Organisation wurde in den Papierkorb verschoben.',
        'restored' => 'Die Organisation wurde wiederhergestellt.',
        'no_billing_email' => 'Die Firma hat keine Rechnungs-E-Mail.',
        'billing_email_verified' => 'Die Rechnungs-E-Mail ist bereits bestätigt.',
        'verification_sent' => 'Die Bestätigungs-E-Mail wurde gesendet.',
        'verification_failed' => 'Die Bestätigungs-E-Mail konnte nicht gesendet werden. Versuche es gleich noch einmal.',
        'subscription_cancelled' => 'Das Abonnement wurde gekündigt.',
        'subscription_active' => 'Das Abonnement ist aktiv.',
        'override_saved' => 'Die Ausnahme wurde gespeichert.',
        'address_added' => 'Die Adresse wurde hinzugefügt.',
        'address_updated' => 'Die Adresse wurde geändert.',
        'contact_added' => 'Die Kontaktperson wurde hinzugefügt.',
        'contact_updated' => 'Der Kontakt wurde geändert.',
    ],

    'product' => [
        'created' => 'Das Projekt wurde angelegt.',
        'updated' => 'Das Projekt wurde geändert.',
        'in_use' => 'Organisationen nutzen dieses Projekt – trenne sie zuerst.',
        'feature_added' => 'Die Funktion wurde dem Katalog hinzugefügt.',
        'feature_updated' => 'Die Funktion wurde geändert.',
        'plan_created' => 'Der Plan wurde angelegt.',
        'plan_updated' => 'Der Plan wurde geändert.',
        'plan_in_use' => 'Der Plan wird verwendet – verschiebe die Kunden zuerst.',
    ],

    'invoice' => [
        'draft_created' => 'Der Entwurf wurde angelegt. Ergänze die Positionen und stelle ihn aus.',
        'trashed' => 'Der Entwurf wurde in den Papierkorb verschoben. Du findest ihn im Filter „Gelöscht“.',
        'restored' => 'Der Entwurf wurde wiederhergestellt.',
        'restored_event' => 'Der Entwurf wurde aus dem Papierkorb wiederhergestellt.',
        'force_deleted' => 'Der Entwurf wurde endgültig gelöscht.',
        'item_added' => 'Die Position wurde hinzugefügt.',
        'item_updated' => 'Die Position wurde geändert.',
        'item_removed' => 'Die Position wurde entfernt.',
        'reminder_sent' => 'Die Mahnung wurde gesendet.',
        'payment_recorded' => 'Die Zahlung wurde erfasst.',
        'cancelled' => 'Der Beleg wurde storniert.',
        'converted' => 'Ein Entwurf der endgültigen Rechnung wurde vorbereitet.',
        'duplicated' => 'Eine Kopie wurde als neuer Entwurf angelegt.',
        'sending_test' => 'Testmodus – Belege und Mahnungen gehen an :email, nicht an Kunden.',
        'sending_off' => 'Der Versand von Belegen ist ausgeschaltet (Rechnungen → Einstellungen).',
        'sending_no_redirect' => 'Im Testmodus ist keine Adresse für den Versand festgelegt (Rechnungen → Einstellungen).',
    ],

    'webhook_updated' => 'Der Webhook wurde geändert.',

    'contact_types' => [
        'general' => 'Allgemein',
        'billing' => 'Abrechnung',
        'technical' => 'Technisch',
        'statutory' => 'Vertretungsberechtigt',
    ],

    'validation' => [
        'quantity_zero' => 'Die Menge darf nicht null sein.',
        'period_end' => 'Das Ende des Zeitraums darf nicht vor seinem Beginn liegen.',
        'due_at' => 'Die Fälligkeit darf nicht vor dem Ausstellungsdatum liegen.',
        'symbol_digits' => ':attribute darf nur Ziffern enthalten.',
        'attributes' => [
            'quantity' => 'Menge',
            'unit_price' => 'Einzelpreis',
            'organization_id' => 'Organisation',
            'issued_at' => 'Ausstellungsdatum',
            'delivered_at' => 'Lieferdatum',
            'due_at' => 'Fälligkeitsdatum',
            'variable_symbol' => 'Variables Symbol',
            'constant_symbol' => 'Konstantes Symbol',
            'specific_symbol' => 'Spezifisches Symbol',
        ],
    ],

    'invoice_errors' => [
        'no_plan' => 'Das Abonnement hat keinen Plan, die Rechnung kann nicht ausgestellt werden.',
        'already_issued' => 'Der Beleg wurde bereits ausgestellt.',
        'no_items' => 'Der Beleg hat keine Positionen.',
        'missing_fields' => 'Der Firma fehlen Rechnungsangaben: :fields.',
        'draft_not_payable' => 'Ein Entwurf kann nicht als bezahlt markiert werden – stelle ihn zuerst aus.',
        'cancelled_not_payable' => 'Ein stornierter Beleg kann nicht bezahlt werden.',
        'draft_no_cancel' => 'Ein Entwurf muss nicht storniert werden – lösche ihn einfach.',
        'paid_use_credit' => 'Für den Beleg ist eine Zahlung erfasst. Stelle eine Gutschrift aus.',
        'credit_only_invoice' => 'Eine Gutschrift wird nur zu einer regulären Rechnung ausgestellt.',
        'credit_not_issued' => 'Zu einer nicht ausgestellten Rechnung ergibt eine Gutschrift keinen Sinn.',
        'credit_no_items' => 'Es sind keine Positionen für die Gutschrift ausgewählt.',
        'not_proforma' => 'Der Quellbeleg ist keine Vorauszahlungsrechnung.',
        'issued_locked' => 'Ein ausgestellter Beleg kann nicht mehr geändert werden. Stelle eine Gutschrift aus.',
        'draft_not_sent' => 'Entwürfe werden nicht gesendet. Stelle den Beleg zuerst aus.',
        'not_overdue' => 'Der Beleg ist nicht überfällig.',
        'do_not_send' => 'Für die Firma ist „Belege nicht senden“ eingestellt – an den Kunden wird nichts gesendet.',
    ],

];
