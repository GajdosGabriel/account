<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation messages – English
    |--------------------------------------------------------------------------
    |
    | Laravel ships English messages for the built-in rules, so here we only
    | override what differs and add this application's own rules and
    | attribute names.
    |
    */

    'url' => 'The :attribute must be a valid address including https://.',

    /* Application specific rules */

    'ico_checksum' => 'The company number (IČO) is invalid – the checksum does not match.',
    'vat_format' => 'The VAT number must be a country code followed by digits, e.g. SK2020123456.',
    'vat_country' => 'The country code in the VAT number is not a valid EU member state.',
    'vat_vies' => 'The VAT number could not be confirmed in VIES – please check the number.',
    'ico_unique_in_group' => 'A company with this number already exists.',

    'custom' => [
        'password' => [
            'min' => 'The password must be at least :min characters.',
        ],
    ],

    'attributes' => [
        'name' => 'name',
        'legal_name' => 'legal name',
        'legal_form' => 'legal form',
        'ico' => 'company number',
        'dic' => 'tax number',
        'ic_dph' => 'VAT number',
        'vat_mode' => 'VAT status',
        'oss_registered' => 'OSS registration',
        'register_court' => 'register court',
        'register_section' => 'section',
        'register_insert' => 'insert number',
        'established_at' => 'establishment date',
        'street' => 'street',
        'street_no' => 'street number',
        'city' => 'city',
        'postal_code' => 'postal code',
        'region' => 'region',
        'country' => 'country',
        'email' => 'email',
        'billing_email' => 'billing email',
        'phone' => 'phone',
        'website' => 'website',
        'bank_name' => 'bank',
        'iban' => 'IBAN',
        'swift' => 'SWIFT',
        'currency' => 'currency',
        'payment_terms_days' => 'payment terms',
        'payment_method' => 'payment method',
        'invoice_language' => 'invoice language',
        'invoice_delivery' => 'invoice delivery',
        'supplier_number' => 'supplier number',
        'status' => 'status',
        'note' => 'note',
        'password' => 'password',
        'current_password' => 'current password',
        'recipient' => 'recipient',
        'label' => 'label',
        'type' => 'type',
        'position' => 'position',
        'metrics' => 'metrics',
        'plan_id' => 'plan',
        'key' => 'key',
        'price_cents' => 'price',
        'interval' => 'billing interval',
        'trial_days' => 'trial days',
        'url' => 'address',
    ],
];
