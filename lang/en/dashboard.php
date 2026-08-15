<?php

return [

    'title' => 'Overview',

    'summary' => [
        'mrr' => 'Monthly recurring revenue',
        'organizations' => 'organizations',
        'active' => 'active subscriptions',
        'trialing' => 'in trial period',
        'payment_issues' => 'with a payment problem',
    ],

    'invoicing' => [
        'heading' => 'Invoicing',
        'all' => 'All documents →',
        'invoiced_month' => 'Invoiced this month',
        'paid_month' => 'Paid this month',
        'outstanding' => 'Outstanding in total',
        'overdue' => 'Overdue',
        'documents' => '{1} :count document|[0,*] :count documents',
    ],

    'forecast' => [
        'title' => 'Revenue forecast',
        'description' => 'What is due within :days days, that is by :until.',
        'note' => 'Nothing is estimated – these are receivables and renewals already on record.',
        'due' => 'Invoices due',
        'renewals' => 'Subscription renewals',
        'at_risk' => 'At risk, past due',
        'avg_days_to_pay' => 'Average time to payment:',
        'days' => '{1} :count day|[0,*] :count days',
        'drafts' => 'Drafts waiting to be issued:',
    ],

    'history' => [
        'title' => 'Invoicing over time',
        'description' => 'The last six months: issued and how much of it was paid.',
        'invoiced' => 'issued',
        'paid' => 'paid',
        'bar_invoiced' => 'Issued: :amount',
        'bar_paid' => 'Paid: :amount',
        'empty' => 'Nothing to plot yet – issue your first document.',
    ],

    'products' => [
        'heading' => 'Connected projects',
        'active' => 'active',
        'inactive' => 'disabled',
        'organizations' => '{1} organization|[0,*] organizations',
        'empty' => 'Add your first project',
    ],

    'attention' => [
        'title' => 'Needs attention',
        'description' => 'Subscriptions past due or suspended.',
        'deadline' => 'until :date',
        'empty' => 'Everything is paid.',
    ],

    'near_limit' => [
        'title' => 'Close to the limit',
        'description' => 'Candidates for a higher plan.',
        'empty' => 'Nobody is close to a limit yet.',
    ],
];
