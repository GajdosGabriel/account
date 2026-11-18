<?php

/*
|--------------------------------------------------------------------------
| Notices after an action (toast) – English
|--------------------------------------------------------------------------
*/

return [

    'saved' => 'Changes have been saved.',

    'auth' => [
        'failed' => 'These credentials do not match any account.',
        'reset_invalid' => 'The password reset link is invalid or has expired.',
        'reset_done' => 'Your password has been changed, you can now sign in.',
        'reset_link_sent' => 'If an account with this e-mail exists, we have sent it a password reset link.',
        'password_changed' => 'Your password has been changed. Other devices have been signed out.',
        'cannot_remove_self' => 'You cannot remove your own access.',
        'cannot_remove_last' => 'The last operator cannot be removed.',
    ],

    'organization' => [
        'created' => 'The organization has been created.',
        'created_verify' => 'The organization has been created. We sent a confirmation request to the billing e-mail.',
        'saved_verify' => 'The details have been saved. We sent a confirmation request to the new billing e-mail.',
        'trashed' => 'The organization has been moved to the trash.',
        'restored' => 'The organization has been restored.',
        'no_billing_email' => 'The company has no billing e-mail.',
        'billing_email_verified' => 'The billing e-mail is already verified.',
        'verification_sent' => 'The verification e-mail has been sent.',
        'verification_failed' => 'The verification e-mail could not be sent. Please try again in a moment.',
        'subscription_cancelled' => 'The subscription has been cancelled.',
        'subscription_active' => 'The subscription is active.',
        'override_saved' => 'The override has been saved.',
        'address_added' => 'The address has been added.',
        'address_updated' => 'The address has been updated.',
        'contact_added' => 'The contact person has been added.',
        'contact_updated' => 'The contact has been updated.',
    ],

    'product' => [
        'created' => 'The project has been created.',
        'updated' => 'The project has been updated.',
        'in_use' => 'Organizations use this project – unlink them first.',
        'feature_added' => 'The feature has been added to the catalogue.',
        'feature_updated' => 'The feature has been updated.',
        'plan_created' => 'The plan has been created.',
        'plan_updated' => 'The plan has been updated.',
        'plan_in_use' => 'The plan is in use – move the customers elsewhere first.',
    ],

    'invoice' => [
        'draft_created' => 'The draft has been created. Add items and issue it.',
        'trashed' => 'The draft has been moved to the trash. You will find it under the “Deleted” filter.',
        'restored' => 'The draft has been restored.',
        'restored_event' => 'The draft was restored from the trash.',
        'force_deleted' => 'The draft has been permanently deleted.',
        'item_added' => 'The item has been added.',
        'item_updated' => 'The item has been updated.',
        'item_removed' => 'The item has been removed.',
        'reminder_sent' => 'The reminder has been sent.',
        'payment_recorded' => 'The payment has been recorded.',
        'cancelled' => 'The document has been cancelled.',
        'converted' => 'A draft of the final invoice has been prepared.',
        'duplicated' => 'A copy has been created as a new draft.',
        'sending_test' => 'Test mode – documents and reminders go to :email, not to customers.',
        'sending_off' => 'Sending documents is turned off (Invoices → Settings).',
        'sending_no_redirect' => 'Test mode has no address to send documents to (Invoices → Settings).',
    ],

    'webhook_updated' => 'The webhook has been updated.',

    'contact_types' => [
        'general' => 'General',
        'billing' => 'Billing',
        'technical' => 'Technical',
        'statutory' => 'Statutory body',
    ],

    'validation' => [
        'quantity_zero' => 'The quantity cannot be zero.',
        'period_end' => 'The end of the period cannot be before its start.',
        'due_at' => 'The due date cannot be before the issue date.',
        'symbol_digits' => 'The :attribute may only contain digits.',
        'attributes' => [
            'quantity' => 'quantity',
            'unit_price' => 'unit price',
            'organization_id' => 'organization',
            'issued_at' => 'issue date',
            'delivered_at' => 'delivery date',
            'due_at' => 'due date',
            'variable_symbol' => 'variable symbol',
            'constant_symbol' => 'constant symbol',
            'specific_symbol' => 'specific symbol',
        ],
    ],

    'invoice_errors' => [
        'no_plan' => 'The subscription has no plan, the invoice cannot be issued.',
        'already_issued' => 'The document has already been issued.',
        'no_items' => 'The document has no items.',
        'missing_fields' => 'The company is missing billing details: :fields.',
        'draft_not_payable' => 'A draft cannot be marked as paid – issue it first.',
        'cancelled_not_payable' => 'A cancelled document cannot be paid.',
        'draft_no_cancel' => 'A draft does not need cancelling – just delete it.',
        'paid_use_credit' => 'The document has a recorded payment. Issue a credit note.',
        'credit_only_invoice' => 'A credit note can only be issued for a final invoice.',
        'credit_not_issued' => 'A credit note makes no sense for an invoice that has not been issued.',
        'credit_no_items' => 'No items are selected for the credit note.',
        'not_proforma' => 'The source document is not a proforma invoice.',
        'issued_locked' => 'An issued document can no longer be changed. Issue a credit note.',
        'draft_not_sent' => 'Drafts are not sent. Issue the document first.',
        'not_overdue' => 'The document is not overdue.',
        'do_not_send' => 'The company is set to “Do not send documents” – nothing will be sent to the customer.',
    ],

];
