<?php

return [

    'edit' => 'Edit token',
    'subtitle' => 'Description and abilities. The token itself cannot be changed – the database only holds its hash.',

    'name' => 'Description',
    'name_hint' => 'Something you will recognise in six months – for example "production server".',
    'abilities' => 'Abilities',
    'abilities_hint' => 'The token only passes the calls it is allowed to make. The rest get a 403.',
    'abilities_required' => 'A token needs at least one ability.',
    'prefix' => 'Prefix',
    'product' => 'Project',
    'last_used' => 'Last used',
    'never' => 'never',
    'created' => 'Created',
    'select_all' => 'Select all',
    'deselect_all' => 'Deselect all',
    'save' => 'Save changes',
    'saving' => 'Saving…',
    'back' => 'Back to overview',
    'saved' => 'Token updated.',
    'revoked' => 'Token revoked.',
    'unrevoked' => 'Token re-enabled – the project can use it again.',

    'issued' => 'New service token',
    'issued_hint' => 'Save it to the project configuration. Only a hash stays in the database, so we cannot show it again – if you lose it, issue a new one.',
    'webhook_secret' => 'Webhook signing key',
    'webhook_secret_hint' => 'Use it to verify the X-Accounts-Signature header. Save it, it will not be shown again.',

    'state' => [
        'revoked' => 'This token is revoked – the project cannot reach the API with it. "Re-enable token" brings it back with the same value as before.',
    ],

    'ability' => [
        'organizations:read' => [
            'label' => 'Read organizations',
            'description' => 'List and detail of the project\'s organizations, lookup by company ID.',
        ],
        'organizations:write' => [
            'label' => 'Write organizations',
            'description' => 'Create and update an organization from the project.',
        ],
        'entitlements:read' => [
            'label' => 'Read entitlements',
            'description' => 'Plan, features and limits – what the project checks before allowing an action.',
        ],
        'usage:write' => [
            'label' => 'Report usage',
            'description' => 'Record measured usage against metrics from the catalogue.',
        ],
    ],
];
