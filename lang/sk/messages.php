<?php

return [

    // Hlášky, ktoré vidí koncový zákazník v pripojenom projekte.

    'token' => [
        'missing' => 'Chýba service token.',
        'invalid' => 'Neplatný alebo zrušený service token.',
        'ability' => 'Token nemá oprávnenie: :ability.',
        'product_inactive' => 'Projekt je deaktivovaný.',
        'no_product' => 'Token nie je priradený k produktu.',
    ],

    'organization' => [
        'not_linked' => 'Organizácia nie je naviazaná na tento projekt.',
        'linked' => 'Organizácia bola naviazaná na projekt.',

        // Názvy chýbajúcich fakturačných údajov. Vypisujú sa v zozname za
        // dvojbodkou („chýba: sídlo, e-mail na faktúry“), preto malé písmeno
        // a jednotné číslo. Vidí ich zákazník vo formulári projektu.
        'billing_missing' => [
            'ico' => 'IČO',
            'address' => 'adresa',
            'registered_address' => 'sídlo',
            'billing_email' => 'e-mail na faktúry',
            'vat_number' => 'IČ DPH',
        ],
    ],

    'registry' => [
        'ico_not_found' => 'IČO sa v registri nenašlo.',
        'ico_disabled' => 'Overovanie IČO je vypnuté.',
        'rpo_unavailable' => 'Register je momentálne nedostupný.',
        'rpo_failed' => 'Register neodpovedal (:status).',
        'vies_disabled' => 'Overovanie IČ DPH je vypnuté.',
        'vies_unavailable' => 'VIES je momentálne nedostupný.',
        'vies_failed' => 'VIES neodpovedal (:status).',
        'vat_prefix' => 'IČ DPH musí začínať kódom krajiny, napríklad SK2020123456.',
    ],

    'subscription' => [
        'no_organization' => 'Účet zatiaľ nepatrí k žiadnej firme.',
        'organization_suspended' => 'Organizácia je pozastavená. Kontaktujte prosím správcu.',
        'no_subscription' => 'Pre tento projekt nemáte predplatné.',
        'past_due' => 'Faktúra je po splatnosti. Prístup potrvá do :date.',
        'suspended' => 'Predplatné je pozastavené – dáta si môžete prezerať, ale nie meniť.',
        'cancelled' => 'Predplatné bolo zrušené.',
        'over_limit' => 'Dosiahli ste limit plánu (:used z :limit). Nové položky sa nedajú pridať.',
    ],
    'invoice' => [
        'locked' => 'Vystavený doklad sa už nedá meniť. Vystav dobropis.',
        'draft_only' => 'Túto akciu možno vykonať len s konceptom.',
        'no_items' => 'Doklad nemá žiadne položky.',
        'missing_billing' => 'Firme chýbajú fakturačné údaje: :fields.',
        'no_email' => 'Firma nemá vyplnený e-mail na faktúry.',
        'already_paid' => 'Doklad je už uhradený.',
        'cancelled' => 'Stornovaný doklad sa nedá ďalej spracovať.',
        'has_payment' => 'Doklad má evidovanú úhradu. Vystav dobropis.',
        'no_series' => 'Pre tento typ dokladu nie je založený číselný rad.',
        'sent' => 'Doklad bol odoslaný na :email.',
        'reminder_sent' => 'Upomienka bola odoslaná.',
        'not_overdue' => 'Doklad nie je po splatnosti.',
        'pdf_missing' => 'Na generovanie PDF chýba knižnica dompdf.',
    ],
];
