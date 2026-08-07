<?php

return [

    'token' => [
        'missing' => 'Chybí service token.',
        'invalid' => 'Neplatný nebo zrušený service token.',
        'ability' => 'Token nemá oprávnění: :ability.',
        'product_inactive' => 'Projekt je deaktivován.',
        'no_product' => 'Token není přiřazen k produktu.',
    ],

    'organization' => [
        'not_linked' => 'Organizace není navázána na tento projekt.',
        'linked' => 'Organizace byla navázána na projekt.',
    ],

    'registry' => [
        'ico_not_found' => 'IČO se v rejstříku nenašlo.',
        'ico_disabled' => 'Ověřování IČO je vypnuto.',
        'rpo_unavailable' => 'Rejstřík je momentálně nedostupný.',
        'rpo_failed' => 'Rejstřík neodpověděl (:status).',
        'vies_disabled' => 'Ověřování DIČ je vypnuto.',
        'vies_unavailable' => 'VIES je momentálně nedostupný.',
        'vies_failed' => 'VIES neodpověděl (:status).',
        'vat_prefix' => 'DIČ musí začínat kódem země, například CZ12345678.',
    ],

    'subscription' => [
        'no_organization' => 'Účet zatím nepatří k žádné firmě.',
        'organization_suspended' => 'Organizace je pozastavena. Kontaktujte prosím správce.',
        'no_subscription' => 'Pro tento projekt nemáte předplatné.',
        'past_due' => 'Faktura je po splatnosti. Přístup potrvá do :date.',
        'suspended' => 'Předplatné je pozastaveno – data si můžete prohlížet, ale ne měnit.',
        'cancelled' => 'Předplatné bylo zrušeno.',
        'over_limit' => 'Dosáhli jste limitu tarifu (:used z :limit). Nové položky nelze přidat.',
    ],
    'invoice' => [
        'locked' => 'Vystavený doklad už nelze měnit. Vystav dobropis.',
        'draft_only' => 'Tuto akci lze provést pouze s konceptem.',
        'no_items' => 'Doklad nemá žádné položky.',
        'missing_billing' => 'Firmě chybí fakturační údaje: :fields.',
        'no_email' => 'Firma nemá vyplněný e-mail pro faktury.',
        'already_paid' => 'Doklad je již uhrazen.',
        'cancelled' => 'Stornovaný doklad nelze dále zpracovat.',
        'has_payment' => 'Doklad má evidovanou úhradu. Vystav dobropis.',
        'no_series' => 'Pro tento typ dokladu není založena číselná řada.',
        'sent' => 'Doklad byl odeslán na :email.',
        'reminder_sent' => 'Upomínka byla odeslána.',
        'not_overdue' => 'Doklad není po splatnosti.',
        'pdf_missing' => 'Pro generování PDF chybí knihovna dompdf.',
    ],
];
