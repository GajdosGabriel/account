<?php

return [

    'subscription_status' => [
        'trialing' => 'Zkušební období',
        'active' => 'Aktivní',
        'past_due' => 'Po splatnosti',
        'suspended' => 'Pozastavené',
        'cancelled' => 'Zrušené',
        'none' => 'Bez předplatného',
    ],

    'invoice_status' => [
        'draft' => 'Koncept',
        'issued' => 'Vystavená',
        'sent' => 'Odeslaná',
        'partially_paid' => 'Částečně uhrazená',
        'paid' => 'Uhrazená',
        'overdue' => 'Po splatnosti',
        'cancelled' => 'Stornovaná',
    ],

    'invoice_type' => [
        'invoice' => 'Faktura – daňový doklad',
        'proforma' => 'Zálohová faktura',
        'credit_note' => 'Dobropis – opravný daňový doklad',
    ],

    'invoice_type_short' => [
        'invoice' => 'Faktura',
        'proforma' => 'Zálohová',
        'credit_note' => 'Dobropis',
    ],

    'legal_form' => [
        'sro' => 'Společnost s ručením omezeným',
        'zivnost' => 'Živnost',
        'as' => 'Akciová společnost',
        'ks' => 'Komanditní společnost',
        'vos' => 'Veřejná obchodní společnost',
        'druzstvo' => 'Družstvo',
        'nezisk' => 'Nezisková organizace',
        'fyzicka' => 'Fyzická osoba',
        'ine' => 'Jiné',
    ],

    'subject_type' => [
        'company' => [
            'label' => 'Organizace',
            'description' => 'Firma, živnostník nebo nezisková organizace s IČO.',
            'name_label' => 'Název firmy',
        ],
        'person' => [
            'label' => 'Soukromá osoba',
            'description' => 'Občan bez IČO. Stačí jméno a adresa.',
            'name_label' => 'Jméno a příjmení',
        ],
    ],

    'vat_mode' => [
        'non_payer' => [
            'label' => 'Neplátce DPH',
            'description' => 'Na faktuře se DPH neuvádí.',
        ],
        'payer' => [
            'label' => 'Plátce DPH (§ 4)',
            'description' => 'Běžný plátce, fakturuje s DPH.',
        ],
        'reg_7' => [
            'label' => 'Registrovaný podle § 7',
            'description' => 'Pořízení zboží z EU nad limit. Není plátce, ale má DIČ pro DPH.',
        ],
        'reg_7a' => [
            'label' => 'Registrovaný podle § 7a',
            'description' => 'Přijímání nebo poskytování služeb z/do EU. Není plátce, ale má DIČ pro DPH.',
        ],
    ],

    'organization_status' => [
        'active' => 'Aktivní',
        'suspended' => 'Pozastavená',
        'archived' => 'Archivovaná',
        'deleted' => 'Smazaná',
    ],
];
