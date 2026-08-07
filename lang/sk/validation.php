<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validačné hlášky – slovenčina
    |--------------------------------------------------------------------------
    |
    | Pokryté sú pravidlá, ktoré táto aplikácia reálne používa, plus tie
    | najbežnejšie. Hlášky chodia aj do formulárov v pripojených projektoch,
    | preto sú písané pre koncového zákazníka, nie pre vývojára.
    |
    */

    'accepted' => 'Pole :attribute musí byť potvrdené.',
    'active_url' => 'Pole :attribute nie je platná adresa.',
    'after' => 'Pole :attribute musí byť dátum po :date.',
    'after_or_equal' => 'Pole :attribute musí byť dátum :date alebo neskorší.',
    'alpha' => 'Pole :attribute môže obsahovať iba písmená.',
    'alpha_dash' => 'Pole :attribute môže obsahovať iba písmená, číslice, pomlčky a podčiarkovníky.',
    'alpha_num' => 'Pole :attribute môže obsahovať iba písmená a číslice.',
    'array' => 'Pole :attribute musí byť zoznam.',
    'before' => 'Pole :attribute musí byť dátum pred :date.',
    'before_or_equal' => 'Pole :attribute musí byť dátum :date alebo skorší.',
    'between' => [
        'array' => 'Pole :attribute musí obsahovať :min až :max položiek.',
        'file' => 'Súbor :attribute musí mať veľkosť :min až :max kilobajtov.',
        'numeric' => 'Pole :attribute musí byť medzi :min a :max.',
        'string' => 'Pole :attribute musí mať :min až :max znakov.',
    ],
    'boolean' => 'Pole :attribute musí mať hodnotu áno alebo nie.',
    'confirmed' => 'Potvrdenie poľa :attribute sa nezhoduje.',
    'current_password' => 'Zadané heslo nie je správne.',
    'date' => 'Pole :attribute nie je platný dátum.',
    'date_equals' => 'Pole :attribute musí byť dátum :date.',
    'date_format' => 'Pole :attribute nezodpovedá formátu :format.',
    'declined' => 'Pole :attribute musí byť odmietnuté.',
    'different' => 'Polia :attribute a :other sa musia líšiť.',
    'digits' => 'Pole :attribute musí mať :digits číslic.',
    'digits_between' => 'Pole :attribute musí mať :min až :max číslic.',
    'email' => 'Pole :attribute musí byť platná e-mailová adresa.',
    'ends_with' => 'Pole :attribute musí končiť na: :values.',
    'enum' => 'Vybraná hodnota poľa :attribute nie je platná.',
    'exists' => 'Vybraná hodnota poľa :attribute neexistuje.',
    'file' => 'Pole :attribute musí byť súbor.',
    'filled' => 'Pole :attribute musí byť vyplnené.',
    'gt' => [
        'numeric' => 'Pole :attribute musí byť väčšie ako :value.',
        'string' => 'Pole :attribute musí mať viac ako :value znakov.',
    ],
    'gte' => [
        'numeric' => 'Pole :attribute musí byť aspoň :value.',
        'string' => 'Pole :attribute musí mať aspoň :value znakov.',
    ],
    'image' => 'Pole :attribute musí byť obrázok.',
    'in' => 'Vybraná hodnota poľa :attribute nie je platná.',
    'integer' => 'Pole :attribute musí byť celé číslo.',
    'ip' => 'Pole :attribute musí byť platná IP adresa.',
    'json' => 'Pole :attribute musí byť platný JSON.',
    'lt' => [
        'numeric' => 'Pole :attribute musí byť menšie ako :value.',
        'string' => 'Pole :attribute musí mať menej ako :value znakov.',
    ],
    'lte' => [
        'numeric' => 'Pole :attribute nesmie byť väčšie ako :value.',
        'string' => 'Pole :attribute nesmie mať viac ako :value znakov.',
    ],
    'max' => [
        'array' => 'Pole :attribute nesmie obsahovať viac ako :max položiek.',
        'file' => 'Súbor :attribute nesmie byť väčší ako :max kilobajtov.',
        'numeric' => 'Pole :attribute nesmie byť väčšie ako :max.',
        'string' => 'Pole :attribute nesmie mať viac ako :max znakov.',
    ],
    'mimes' => 'Pole :attribute musí byť súbor typu: :values.',
    'min' => [
        'array' => 'Pole :attribute musí obsahovať aspoň :min položiek.',
        'file' => 'Súbor :attribute musí mať aspoň :min kilobajtov.',
        'numeric' => 'Pole :attribute musí byť aspoň :min.',
        'string' => 'Pole :attribute musí mať aspoň :min znakov.',
    ],
    'not_in' => 'Vybraná hodnota poľa :attribute nie je platná.',
    'numeric' => 'Pole :attribute musí byť číslo.',
    'present' => 'Pole :attribute musí byť prítomné.',
    'prohibited' => 'Pole :attribute nie je povolené.',
    'regex' => 'Pole :attribute má nesprávny formát.',
    'required' => 'Pole :attribute je povinné.',
    'required_if' => 'Pole :attribute je povinné, keď :other je :value.',
    'required_with' => 'Pole :attribute je povinné, keď je vyplnené :values.',
    'required_without' => 'Pole :attribute je povinné, keď nie je vyplnené :values.',
    'same' => 'Polia :attribute a :other sa musia zhodovať.',
    'size' => [
        'array' => 'Pole :attribute musí obsahovať :size položiek.',
        'file' => 'Súbor :attribute musí mať :size kilobajtov.',
        'numeric' => 'Pole :attribute musí byť :size.',
        'string' => 'Pole :attribute musí mať presne :size znakov.',
    ],
    'starts_with' => 'Pole :attribute musí začínať na: :values.',
    'string' => 'Pole :attribute musí byť text.',
    'timezone' => 'Pole :attribute musí byť platné časové pásmo.',
    'unique' => 'Hodnota poľa :attribute je už použitá.',
    'uploaded' => 'Súbor :attribute sa nepodarilo nahrať.',
    'url' => 'Pole :attribute musí byť platná adresa vrátane https://.',
    'uuid' => 'Pole :attribute musí byť platné UUID.',

    /*
    |--------------------------------------------------------------------------
    | Vlastné pravidlá tejto aplikácie
    |--------------------------------------------------------------------------
    */

    'ico_checksum' => 'IČO nie je platné – nesedí kontrolná číslica.',
    'vat_format' => 'IČ DPH musí mať tvar kód krajiny + číslo, napríklad SK2020123456.',
    'vat_country' => 'Kód krajiny v IČ DPH nie je platný členský štát EÚ.',
    'vat_vies' => 'IČ DPH sa nepodarilo overiť vo VIES – skontrolujte číslo.',
    'ico_unique_in_group' => 'Firma s týmto IČO už v systéme existuje.',

    'custom' => [
        'password' => [
            'min' => 'Heslo musí mať aspoň :min znakov.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Názvy polí
    |--------------------------------------------------------------------------
    */

    'attributes' => [
        'name' => 'názov',
        'legal_name' => 'obchodné meno',
        'legal_form' => 'právna forma',
        'ico' => 'IČO',
        'dic' => 'DIČ',
        'ic_dph' => 'IČ DPH',
        'vat_mode' => 'vzťah k DPH',
        'oss_registered' => 'registrácia v OSS',
        'register_court' => 'registrový súd',
        'register_section' => 'oddiel',
        'register_insert' => 'vložka',
        'established_at' => 'dátum vzniku',
        'street' => 'ulica',
        'street_no' => 'číslo',
        'city' => 'mesto',
        'postal_code' => 'PSČ',
        'region' => 'kraj',
        'country' => 'krajina',
        'email' => 'e-mail',
        'billing_email' => 'fakturačný e-mail',
        'phone' => 'telefón',
        'website' => 'web',
        'bank_name' => 'banka',
        'iban' => 'IBAN',
        'swift' => 'SWIFT',
        'currency' => 'mena',
        'payment_terms_days' => 'splatnosť',
        'payment_method' => 'spôsob platby',
        'invoice_language' => 'jazyk faktúry',
        'invoice_delivery' => 'doručovanie faktúr',
        'supplier_number' => 'naše číslo u zákazníka',
        'status' => 'stav',
        'note' => 'poznámka',
        'password' => 'heslo',
        'current_password' => 'súčasné heslo',
        'recipient' => 'príjemca',
        'label' => 'označenie',
        'type' => 'typ',
        'position' => 'funkcia',
        'metrics' => 'metriky',
        'plan_id' => 'plán',
        'key' => 'kľúč',
        'price_cents' => 'cena',
        'interval' => 'obdobie',
        'trial_days' => 'skúšobné dni',
        'url' => 'adresa',
    ],
];
