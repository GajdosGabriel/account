<?php

return [

    'subscription_status' => [
        'trialing' => 'Trial period',
        'active' => 'Active',
        'past_due' => 'Past due',
        'suspended' => 'Suspended',
        'cancelled' => 'Cancelled',
        'none' => 'No subscription',
    ],

    'invoice_status' => [
        'draft' => 'Draft',
        'issued' => 'Issued',
        'sent' => 'Sent',
        'partially_paid' => 'Partially paid',
        'paid' => 'Paid',
        'overdue' => 'Overdue',
        'cancelled' => 'Cancelled',
    ],

    'invoice_type' => [
        'invoice' => 'Invoice – tax document',
        'proforma' => 'Proforma invoice',
        'credit_note' => 'Credit note – corrective tax document',
    ],

    'invoice_type_short' => [
        'invoice' => 'Invoice',
        'proforma' => 'Proforma',
        'credit_note' => 'Credit note',
    ],

    'legal_form' => [
        'sro' => 'Limited liability company',
        'zivnost' => 'Sole trader',
        'as' => 'Joint-stock company',
        'ks' => 'Limited partnership',
        'vos' => 'General partnership',
        'druzstvo' => 'Cooperative',
        'nezisk' => 'Non-profit organization',
        'fyzicka' => 'Natural person',
        'ine' => 'Other',
    ],

    'subject_type' => [
        'company' => [
            'label' => 'Organization',
            'description' => 'A company, sole trader or non-profit with a company number.',
            'name_label' => 'Company name',
        ],
        'person' => [
            'label' => 'Private individual',
            'description' => 'A person without a company number. Name and address are enough.',
            'name_label' => 'First and last name',
        ],
    ],

    'vat_mode' => [
        'non_payer' => [
            'label' => 'Not VAT registered',
            'description' => 'No VAT is shown on the invoice.',
        ],
        'payer' => [
            'label' => 'VAT payer (§ 4)',
            'description' => 'A regular VAT payer, invoices with VAT.',
        ],
        'reg_7' => [
            'label' => 'Registered under § 7',
            'description' => 'Acquiring goods from the EU above the threshold. Not a VAT payer, but has a VAT number.',
        ],
        'reg_7a' => [
            'label' => 'Registered under § 7a',
            'description' => 'Receiving or supplying services from/to the EU. Not a VAT payer, but has a VAT number.',
        ],
    ],

    'organization_status' => [
        'active' => 'Active',
        'suspended' => 'Suspended',
        'archived' => 'Archived',
        'deleted' => 'Deleted',
    ],
];
