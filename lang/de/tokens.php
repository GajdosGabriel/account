<?php

return [

    'edit' => 'Token bearbeiten',
    'subtitle' => 'Beschreibung und Berechtigungen. Der Token selbst lässt sich nicht ändern – in der Datenbank liegt nur sein Hash.',

    'name' => 'Beschreibung',
    'name_hint' => 'Woran du den Token in einem halben Jahr erkennst – zum Beispiel „Produktivserver“.',
    'abilities' => 'Berechtigungen',
    'abilities_hint' => 'Der Token kommt nur durch die erlaubten Aufrufe. Alles andere bekommt 403.',
    'abilities_required' => 'Ein Token braucht mindestens eine Berechtigung.',
    'prefix' => 'Präfix',
    'product' => 'Projekt',
    'last_used' => 'Zuletzt verwendet',
    'never' => 'nie',
    'created' => 'Erstellt',
    'select_all' => 'Alle auswählen',
    'deselect_all' => 'Auswahl aufheben',
    'save' => 'Änderungen speichern',
    'saving' => 'Speichere…',
    'back' => 'Zurück zur Übersicht',
    'saved' => 'Token wurde bearbeitet.',
    'revoked' => 'Token wurde widerrufen.',
    'unrevoked' => 'Token ist wieder freigegeben – das Projekt kann ihn erneut verwenden.',

    'issued' => 'Neues Service-Token',
    'issued_hint' => 'Speichere es in der Projektkonfiguration. In der Datenbank bleibt nur ein Hash, wir können es nicht erneut anzeigen – bei Verlust ein neues erzeugen.',
    'webhook_secret' => 'Signaturschlüssel des Webhooks',
    'webhook_secret_hint' => 'Damit prüfst du den Header X-Accounts-Signature. Speichere ihn, er wird nicht erneut angezeigt.',

    'state' => [
        'revoked' => 'Dieser Token ist widerrufen – das Projekt kommt damit nicht an die API. Über „Token wieder freigeben“ kommt er mit demselben Wert zurück.',
        'trashed' => 'Dieser Token liegt im Papierkorb. Er kann wiederhergestellt oder endgültig gelöscht werden.',
    ],

    'ability' => [
        'organizations:read' => [
            'label' => 'Firmen lesen',
            'description' => 'Liste und Detail der Organisationen des Projekts, Suche nach Firmennummer.',
        ],
        'organizations:write' => [
            'label' => 'Firmen schreiben',
            'description' => 'Organisation aus dem Projekt anlegen und ändern.',
        ],
        'entitlements:read' => [
            'label' => 'Limits lesen',
            'description' => 'Plan, Funktionen und Limits – danach entscheidet das Projekt, was es zulässt.',
        ],
        'usage:write' => [
            'label' => 'Verbrauch melden',
            'description' => 'Gemessenen Verbrauch zu Metriken aus dem Katalog schreiben.',
        ],
    ],
];
