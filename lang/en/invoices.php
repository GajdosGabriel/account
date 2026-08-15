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
];
