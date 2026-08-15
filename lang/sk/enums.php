<?php

return [

    // Popisky číselníkov (PHP enumov). Drží ich lang, nie `match` v enume –
    // rovnaký text potrebuje server (PDF, e-mail) aj Vue (StatusBadge).
    // Kľúč je vždy hodnota enumu, aby sa dal preklad zložiť priamo z nej.

    'subscription_status' => [
        'trialing' => 'Skúšobné obdobie',
        'active' => 'Aktívne',
        'past_due' => 'Po splatnosti',
        'suspended' => 'Pozastavené',
        'cancelled' => 'Zrušené',
        'none' => 'Bez predplatného',
    ],

    'invoice_status' => [
        'draft' => 'Koncept',
        'issued' => 'Vystavená',
        'sent' => 'Odoslaná',
        'partially_paid' => 'Čiastočne uhradená',
        'paid' => 'Uhradená',
        'overdue' => 'Po splatnosti',
        'cancelled' => 'Stornovaná',
    ],

    'invoice_type' => [
        'invoice' => 'Faktúra – daňový doklad',
        'proforma' => 'Zálohová faktúra',
        'credit_note' => 'Dobropis – opravný daňový doklad',
    ],

    // Do stĺpca v zozname, kde na celý názov nie je miesto.
    'invoice_type_short' => [
        'invoice' => 'Faktúra',
        'proforma' => 'Zálohová',
        'credit_note' => 'Dobropis',
    ],

    'legal_form' => [
        'sro' => 'Spoločnosť s ručením obmedzeným',
        'zivnost' => 'Živnosť',
        'as' => 'Akciová spoločnosť',
        'ks' => 'Komanditná spoločnosť',
        'vos' => 'Verejná obchodná spoločnosť',
        'druzstvo' => 'Družstvo',
        'nezisk' => 'Nezisková organizácia',
        'fyzicka' => 'Fyzická osoba',
        'ine' => 'Iné',
    ],

    'subject_type' => [
        'company' => [
            'label' => 'Organizácia',
            'description' => 'Firma, živnostník alebo nezisková organizácia s IČO.',
            'name_label' => 'Názov firmy',
        ],
        'person' => [
            'label' => 'Súkromná osoba',
            'description' => 'Občan bez IČO. Stačí meno a adresa.',
            'name_label' => 'Meno a priezvisko',
        ],
    ],

    'vat_mode' => [
        'non_payer' => [
            'label' => 'Neplatiteľ DPH',
            'description' => 'Na faktúre sa DPH neuvádza.',
        ],
        'payer' => [
            'label' => 'Platiteľ DPH (§ 4)',
            'description' => 'Bežný platiteľ, fakturuje s DPH.',
        ],
        'reg_7' => [
            'label' => 'Registrovaný podľa § 7',
            'description' => 'Nadobúdanie tovaru z EÚ nad limit. Nie je platiteľ, ale má IČ DPH.',
        ],
        'reg_7a' => [
            'label' => 'Registrovaný podľa § 7a',
            'description' => 'Prijímanie alebo dodávanie služieb z/do EÚ. Nie je platiteľ, ale má IČ DPH.',
        ],
    ],

    'organization_status' => [
        'active' => 'Aktívna',
        'suspended' => 'Pozastavená',
        'archived' => 'Archivovaná',
        'deleted' => 'Zmazaná',
    ],
];
