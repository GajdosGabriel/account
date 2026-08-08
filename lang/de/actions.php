<?php

return [

    'menu' => 'Aktionen',
    'cancel' => 'Abbrechen',
    'show_password' => 'Passwort anzeigen',
    'hide_password' => 'Passwort verbergen',
    'empty' => 'Keine Aktionen verfügbar',
    'disabled' => 'Diese Aktion ist hier nicht erlaubt',
    'trashed' => 'im Papierkorb',

    'view' => 'Anzeigen',
    'edit' => 'Bearbeiten',
    'delete' => 'Löschen',
    'restore' => 'Aus dem Papierkorb holen',
    'force_delete' => 'Endgültig löschen',

    'confirm' => [
        'delete' => '„:name“ in den Papierkorb verschieben? Kann wiederhergestellt werden.',
        'restore' => '„:name“ aus dem Papierkorb wiederherstellen?',
        'force_delete' => '„:name“ endgültig löschen? Das lässt sich nicht rückgängig machen.',
    ],

    'flash' => [
        'deleted' => 'In den Papierkorb verschoben.',
        'restored' => 'Aus dem Papierkorb wiederhergestellt.',
        'force_deleted' => 'Endgültig gelöscht.',
    ],

    'invoice' => [
        'view' => 'Detail öffnen',
        'preview' => 'Rechnungsvorschau',
        'download' => 'PDF herunterladen',
        'issue' => 'Beleg ausstellen',
        'send' => 'Per E-Mail senden',
        'resend' => 'Erneut per E-Mail senden',
        'remind' => 'Mahnung senden',
        'pay' => 'Zahlung erfassen',
        'edit' => 'Entwurf bearbeiten',
        'duplicate' => 'Kopie erstellen',
        'convert' => 'Rechnung aus Anzahlung ausstellen',
        'credit' => 'Gutschrift ausstellen',
        'cancel' => 'Stornieren',
        'delete' => 'Entwurf löschen',
        'restore' => 'Aus dem Papierkorb holen',
        'force_delete' => 'Endgültig löschen',
        'disabled' => 'Diese Aktion ist für diesen Beleg nicht erlaubt',
        'days' => ':count Tage',

        'confirm' => [
            'issue' => 'Mit dem Ausstellen wird der Beleg gesperrt und nummeriert. Fortfahren?',
            'remind' => 'Dem Kunden eine Mahnung senden?',
            'cancel' => 'Diesen Beleg wirklich stornieren?',
            'delete' => 'Entwurf in den Papierkorb verschieben? Kann wiederhergestellt werden.',
            'force_delete' => 'Endgültiges Löschen. Wirklich fortfahren?',
        ],
    ],

    'token' => [
        'revoke' => 'Token widerrufen',
        'unrevoke' => 'Token wieder freigeben',
        'confirm' => [
            'revoke' => 'Token „:name“ widerrufen? Das Projekt verliert sofort den API-Zugriff.',
            'unrevoke' => 'Token „:name“ wieder freigeben? Das Projekt erreicht die API erneut – mit demselben Wert wie zuvor.',
        ],
    ],
];
