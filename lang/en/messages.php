<?php

return [

    'token' => [
        'missing' => 'Service token is missing.',
        'invalid' => 'Invalid or revoked service token.',
        'ability' => 'The token lacks the ability: :ability.',
        'product_inactive' => 'The project is disabled.',
        'no_product' => 'The token is not assigned to a product.',
    ],

    'organization' => [
        'not_linked' => 'The organization is not linked to this project.',
        'linked' => 'The organization has been linked to the project.',
    ],

    'registry' => [
        'ico_not_found' => 'The company number was not found in the register.',
        'ico_disabled' => 'Company number verification is disabled.',
        'rpo_unavailable' => 'The register is currently unavailable.',
        'rpo_failed' => 'The register did not respond (:status).',
        'vies_disabled' => 'VAT number verification is disabled.',
        'vies_unavailable' => 'VIES is currently unavailable.',
        'vies_failed' => 'VIES did not respond (:status).',
        'vat_prefix' => 'The VAT number must start with a country code, e.g. SK2020123456.',
    ],

    'subscription' => [
        'no_organization' => 'This account does not belong to any company yet.',
        'organization_suspended' => 'The organization is suspended. Please contact your administrator.',
        'no_subscription' => 'You have no subscription for this project.',
        'past_due' => 'The invoice is overdue. Access remains until :date.',
        'suspended' => 'The subscription is suspended – you can read data but not change it.',
        'cancelled' => 'The subscription has been cancelled.',
        'over_limit' => 'You have reached the plan limit (:used of :limit). New items cannot be added.',
    ],
    'invoice' => [
        'locked' => 'An issued document can no longer be changed. Issue a credit note instead.',
        'draft_only' => 'This action is only available for drafts.',
        'no_items' => 'The document has no line items.',
        'missing_billing' => 'The company is missing billing details: :fields.',
        'no_email' => 'The company has no billing e-mail address.',
        'already_paid' => 'The document has already been paid.',
        'cancelled' => 'A cancelled document cannot be processed further.',
        'has_payment' => 'A payment is recorded against this document. Issue a credit note instead.',
        'no_series' => 'No number series is configured for this document type.',
        'sent' => 'The document was sent to :email.',
        'reminder_sent' => 'The reminder has been sent.',
        'not_overdue' => 'The document is not overdue.',
        'pdf_missing' => 'PDF generation requires the dompdf library.',
    ],
];
