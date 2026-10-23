<?php

return [

    'title' => 'Invoices',
    'subtitle' => 'Documents, receivables and exports for your accountant',
    'create' => 'New document',
    'records' => 'documents',
    'documents' => '{1} :count document|[0,*] :count documents',

    'export' => [
        'label' => 'Export',
        'csv' => 'CSV for Excel',
        'xml' => 'XML for accounting',
    ],

    'stats' => [
        'drafts' => 'Drafts',
        'drafts_hint' => 'waiting to be issued',
        'unpaid' => 'Unpaid',
        'overdue' => 'Overdue',
        'paid_month' => 'Paid this month',
        'paid_month_hint' => 'payments received',
    ],

    'filter' => [
        'search' => 'Search',
        'search_placeholder' => 'Number, VS, company or company number…',
        'all_statuses' => 'All statuses',
        'overdue' => 'Overdue',
        'unpaid' => 'Unpaid',
        'status' => 'Status',
        'type' => 'Document type',
        'trashed' => 'Trash (:count)',
        'all_types' => 'All types',
        'from' => 'Issued from',
        'to' => 'Issued to',
        'found' => 'Found: :count',
    ],

    'table' => [
        'document' => 'Document',
        'customer' => 'Customer',
        'issued' => 'Issued',
        'due' => 'Due',
        'total' => 'Amount',
        'status' => 'Status',
        'days_overdue' => '{1} :count day overdue|[0,*] :count days overdue',
        'outstanding' => ':amount remaining',
    ],

    'empty' => [
        'title' => 'No documents',
        'filtered' => 'Try relaxing the filters.',
        'none' => 'Issue your first invoice or run automatic invoicing.',
    ],

    // Invoicing settings (/invoices/settings).
    'settings' => [
        'link' => 'Settings',
        'title' => 'Invoicing settings',
        'subtitle' => 'Reminders, sending, document defaults and automatic billing',
        'back' => 'Invoices',
        'save' => 'Save settings',
        'saved' => 'Settings saved.',
        'unchanged' => 'Nothing changed.',
        'reset' => 'Restore defaults',
        'reset_confirm' => 'Reset all invoicing settings to the configured (.env) values?',
        'reset_done' => 'Settings were reset to defaults.',
        'unsaved' => 'You have unsaved changes.',
        'default_hint' => 'Default: :value',
        'on' => 'on',
        'off' => 'off',
        'none' => 'empty',

        'reminders' => [
            'title' => 'Reminders',
            'description' => 'Automatic reminders for overdue invoices.',
            'enabled' => 'Send reminders automatically',
            'enabled_hint' => 'Sent on weekdays at 9:30, at most one per day and each level only once. You can always send one manually from the invoice detail.',
            'disabled_note' => 'Automatic reminders are off – overdue invoices are only marked.',
            'overdue_now' => '{0} No document is overdue right now.|{1} :count document is overdue right now.|[2,*] :count documents are overdue right now.',
            'levels' => 'Levels and timing',
            'levels_hint' => 'A stricter level must come later than a milder one. Common practice: 3 – 10 – 21 days.',
            'days_after' => 'days after due date',
            'tones' => [
                'friendly' => 'Friendly reminder',
                'firm' => 'Payment reminder',
                'final' => 'Final notice',
            ],
            'tone_hints' => [
                'friendly' => '“It may have slipped your mind…” – a polite nudge.',
                'firm' => 'A firm request for payment.',
                'final' => 'Announces service suspension once the deadline passes.',
            ],
            'min_amount' => 'Ignore balances below',
            'min_amount_hint' => 'E.g. rounding or bank fees. 0 = chase everything.',
            'final_grace_days' => 'Deadline in the final notice',
            'final_grace_days_hint' => '“If we do not receive payment within X days, we will suspend the service.”',
            'days' => 'days',
            'attach_pdf' => 'Attach invoice PDF',
            'copy_to_supplier' => 'Send a blind copy to our e-mail',
            'copy_to_supplier_hint' => 'Copy goes to :email.',
            'no_supplier_email' => 'Supplier has no e-mail set – no copy will be sent.',
        ],

        'sending' => [
            'title' => 'Sending documents',
            'description' => 'Who receives invoices and how.',
            'require_verified' => 'Send only to a verified billing e-mail',
            'require_verified_hint' => 'Protects against typos in the address. Companies without a verified e-mail will not receive the invoice.',
            'bcc' => 'Blind copy of sent invoices',
            'bcc_hint' => 'E.g. your accountant’s inbox or an archive. Empty = none.',
            'mode' => 'Sending mode',
            'mode_hint' => 'Applies to manual sending, automatic sending in subscription billing and reminders. To stop a single company, set its Invoice delivery to “Do not send documents”.',
            'modes' => [
                'live' => 'Live',
                'redirect' => 'Test – everything to my address',
                'off' => 'Off',
            ],
            'mode_hints' => [
                'live' => 'Documents and reminders go to customers.',
                'redirect' => 'Customers get nothing. You get the e-mail marked [TEST] and the document status does not change.',
                'off' => 'Nothing goes out. Invoices can still be issued, they are just not sent.',
            ],
            'redirect_to' => 'Test address',
            'redirect_to_hint' => 'All documents and reminders go here instead of to customers.',
            'off_note' => 'Sending is off – the “Send” and “Send reminder” buttons are hidden and automatic sending is skipped.',
        ],

        'defaults' => [
            'title' => 'Document defaults',
            'description' => 'Prefilled in every new draft, can be changed there.',
            'due_days' => 'Payment terms',
            'due_days_hint' => 'Applies to companies without their own terms. Usually 14 days.',
            'constant_symbol' => 'Constant symbol',
            'constant_symbol_hint' => '0308 = cashless payment for services.',
            'unit' => 'Unit',
            'vat_rate' => 'Default VAT rate',
            'footer_note' => 'Note below items',
            'footer_note_hint' => 'E.g. “Thank you for your business.” or a late-payment interest notice.',
        ],

        'qr' => [
            'title' => 'QR payment',
            'description' => 'QR code on the invoice and in e-mails – the customer scans it in their banking app.',
            'enabled' => 'Show QR code',
            'format' => 'Format',
            'formats' => [
                'pay_by_square' => 'PAY by square (Slovak banks)',
                'epc' => 'EPC / SEPA (AT, DE and other EU banks)',
            ],
        ],

        'billing_run' => [
            'title' => 'Automatic subscription billing',
            'description' => 'Every morning at 6:00, documents are created for subscriptions whose period is ending.',
            'issue_days_before' => 'Invoice in advance',
            'issue_days_before_hint' => 'How many days before the period ends to invoice the next one.',
            'auto_issue' => 'Issue right away (assign number)',
            'auto_issue_hint' => 'Off = drafts are created for review.',
            'auto_send' => 'Send issued invoices to customers immediately',
            'auto_send_hint' => 'Also turns on issuing – drafts are never sent.',
            'skip_zero' => 'Do not invoice free plans',
        ],

        'errors' => [
            'no_level' => 'Enable at least one reminder level or turn reminders off.',
            'level_order' => 'Each next level must come later than the previous one.',
        ],
    ],
];
