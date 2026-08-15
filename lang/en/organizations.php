<?php

return [

    'title' => 'Organizations',
    'subtitle' => 'One source of truth for every connected project.',
    'create' => 'New organization',
    'records' => 'organizations',

    'table' => [
        'name' => 'Name',
        'ico' => 'Company number',
        'vat_number' => 'VAT number',
        'city' => 'City',
        'status' => 'Status',
        'products' => 'Projects',
        'verified' => 'verified',
    ],

    'empty' => [
        'trashed' => 'There is no organization in the trash.',
        'filtered' => 'No organization matches the filter.',
        'none' => 'There is no organization yet.',
    ],

    'filter' => [
        'search' => 'Search',
        'search_placeholder' => 'Search by name or company number…',
        'product' => 'Project',
        'all_products' => 'All projects',
        'status' => 'Status',
        'all_statuses' => 'All statuses',
        'trashed' => 'Trash (:count)',
        'linked' => 'Link to a project',
        'linked_any' => 'Linked and unlinked',
        'linked_only' => 'Linked to a project only',
        'linked_none' => 'Without a project only',

        'statuses' => [
            'active' => 'Active',
            'suspended' => 'Suspended',
            'archived' => 'Archived',
        ],
    ],

    'form' => [
        'create' => 'New organization',
        'edit' => 'Edit organization',
        'intro' => 'These details are used by every connected project and by invoicing.',

        'subject' => [
            'title' => 'Customer type',
            'description' => 'A private individual is never asked for company details – they will never have a company number.',
        ],

        'identification' => [
            'title' => 'Identification',
            'description' => 'The company number is loaded from the register (SK: RPO, CZ: ARES), the VAT number is verified in VIES.',
            'description_person' => 'A private individual only needs a name. The address below goes on the invoice.',
        ],

        'lookup' => [
            'fetch' => 'Load',
            'verify' => 'Verify',
            'filled' => 'Details filled in from the :register register.',
            'not_found' => 'The company number was not found.',
            'registry_down' => 'The register is unavailable.',
            'vat_valid' => 'Valid in VIES',
            'vat_invalid' => 'The VAT number is not valid.',
            'vies_down' => 'VIES is unavailable.',
        ],

        'existing' => [
            'intro' => 'We already have a company with this company number —',
            'deleted' => '(deleted)',
            'archived' => '(archived)',
            'suspended' => '(suspended)',
            'filled' => 'The details were filled in from it.',
            'open' => 'Open the existing one',
            'duplicate' => '— saving a new one would create a duplicate.',
        ],

        'fields' => [
            'ico' => 'Company number',
            'name' => 'Display name',
            'name_person' => 'First and last name',
            'legal_name' => 'Legal name',
            'legal_name_hint' => '— exactly as in the register',
            'legal_form' => 'Legal form',
            'dic' => 'Tax number',
            'vat_mode' => 'VAT status',
            'ic_dph' => 'VAT number',
            'oss' => 'Registered for OSS (sales within the EU)',
            'register_court' => 'Registry court / office',
            'register_section' => 'Section',
            'register_insert' => 'Insert',
            'established_at' => 'Established',
            'street' => 'Street',
            'street_no' => 'Number',
            'postal_code' => 'Postal code',
            'city' => 'City',
            'country' => 'Country',
            'region' => 'Region',
            'email' => 'General e-mail',
            'billing_email' => 'Billing e-mail',
            'phone' => 'Phone',
            'website' => 'Website',
            'bank_name' => 'Bank',
            'iban' => 'IBAN',
            'swift' => 'SWIFT',
            'payment_terms_days' => 'Payment terms (days)',
            'payment_method' => 'Payment method',
            'currency' => 'Currency',
            'invoice_delivery' => 'Invoice delivery',
            'invoice_language' => 'Invoice language',
            'supplier_number' => 'Our number at the customer',
            'status' => 'Organization status',
            'note' => 'Note',
        ],

        'placeholders' => [
            'legal_name' => 'Company Ltd.',
            'register_court' => 'District Court Bratislava I',
            'street' => 'Main Street',
            'region' => 'Bratislava Region',
            'bank_name' => 'Tatra banka',
        ],

        'register' => [
            'title' => 'Register entry',
            'description' => 'Required in the invoice footer and in business correspondence.',
        ],

        'address' => [
            'title' => 'Registered address / place of business',
            'title_person' => 'Address',
            'description' => 'The address as stated in the trade licence or the commercial register extract.',
            'description_person' => 'Permanent address – it is printed on the invoice.',
            'more' => 'Mailing, delivery and branch addresses are added on the organization detail.',
        ],

        'contact' => [
            'title' => 'Contact and bank details',
        ],

        'billing' => [
            'title' => 'Invoicing preferences',
            'payment_methods' => [
                'transfer' => 'Bank transfer',
                'card' => 'Card',
                'cash' => 'Cash',
                'cod' => 'Cash on delivery',
            ],
            'delivery' => [
                'email' => 'By e-mail',
                'post' => 'By post',
                'both' => 'By e-mail and post',
            ],
            'languages' => [
                'sk' => 'Slovak',
                'en' => 'English',
                'de' => 'German',
            ],
        ],

        'internal' => [
            'title' => 'Internal',
            'description' => 'Visible to the operator only, never sent to the projects.',
            'statuses' => [
                'active' => 'Active',
                'suspended' => 'Suspended — projects will lock it',
                'archived' => 'Archived',
            ],
        ],
    ],
];
