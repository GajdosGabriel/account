<?php

return [

    'menu' => 'Actions',
    'cancel' => 'Cancel',
    'show_password' => 'Show password',
    'hide_password' => 'Hide password',
    'empty' => 'No actions available',
    'disabled' => 'This action is not allowed here',
    'trashed' => 'in trash',

    'view' => 'View',
    'edit' => 'Edit',
    'delete' => 'Delete',
    'restore' => 'Restore from trash',
    'force_delete' => 'Delete permanently',

    'confirm' => [
        'delete' => 'Move ":name" to the trash? It can be restored later.',
        'restore' => 'Restore ":name" from the trash?',
        'force_delete' => 'Permanently delete ":name"? This cannot be undone.',
    ],

    'flash' => [
        'deleted' => 'Moved to the trash.',
        'restored' => 'Restored from the trash.',
        'force_deleted' => 'Permanently deleted.',
    ],

    'invoice' => [
        'view' => 'Open detail',
        'preview' => 'Preview invoice',
        'download' => 'Download PDF',
        'issue' => 'Issue document',
        'send' => 'Send by e-mail',
        'resend' => 'Send by e-mail again',
        'remind' => 'Send reminder',
        'pay' => 'Record payment',
        'edit' => 'Edit draft',
        'duplicate' => 'Duplicate',
        'convert' => 'Issue invoice from proforma',
        'credit' => 'Issue credit note',
        'cancel' => 'Cancel document',
        'delete' => 'Delete draft',
        'restore' => 'Restore from trash',
        'force_delete' => 'Delete permanently',
        'disabled' => 'This action is not allowed for this document',
        'days' => ':count days',

        'confirm' => [
            'issue' => 'Issuing locks the document and assigns a number. Continue?',
            'remind' => 'Send a reminder to the customer?',
            'cancel' => 'Really cancel this document?',
            'delete' => 'Move the draft to the trash? It can be restored later.',
            'force_delete' => 'This deletes the document for good. Continue?',
        ],
    ],

    'token' => [
        'revoke' => 'Revoke token',
        'unrevoke' => 'Re-enable token',
        'confirm' => [
            'revoke' => 'Revoke token ":name"? The project loses API access immediately.',
            'unrevoke' => 'Re-enable token ":name"? The project can reach the API again with the same value as before.',
        ],
    ],
];
