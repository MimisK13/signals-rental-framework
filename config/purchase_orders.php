<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Purchase order types
    |--------------------------------------------------------------------------
    */

    'default_type' => 'purchase',

    'types' => [
        'sub_hire' => [
            'label' => 'Sub-hire',
        ],
        'purchase' => [
            'label' => 'Purchase',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Purchase order lifecycle statuses
    |--------------------------------------------------------------------------
    */

    'default_status' => 'draft',

    'statuses' => [
        'draft' => [
            'label' => 'Draft',
            'terminal' => false,
            'badge_class' => 's-badge-gray',
        ],
        'sent' => [
            'label' => 'Sent',
            'terminal' => false,
            'badge_class' => 's-badge-blue',
        ],
        'partially_received' => [
            'label' => 'Partially received',
            'terminal' => false,
            'badge_class' => 's-badge-amber',
        ],
        'received' => [
            'label' => 'Received',
            'terminal' => true,
            'badge_class' => 's-badge-green',
        ],
        'cancelled' => [
            'label' => 'Cancelled',
            'terminal' => true,
            'badge_class' => 's-badge-red',
        ],
    ],

    'transitions' => [
        'draft' => ['sent', 'cancelled'],
        'sent' => ['partially_received', 'cancelled'],
        'partially_received' => ['received'],
        'received' => [],
        'cancelled' => [],
    ],

    'fallback_number_prefix' => 'PO-',

];
