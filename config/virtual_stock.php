<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Virtual stock intake lifecycle statuses
    |--------------------------------------------------------------------------
    */

    'default_status' => 'draft',

    'statuses' => [
        'draft' => [
            'label' => 'Draft',
            'terminal' => false,
            'badge_class' => 's-badge-gray',
        ],
        'confirmed' => [
            'label' => 'Confirmed',
            'terminal' => false,
            'badge_class' => 's-badge-blue',
        ],
        'received' => [
            'label' => 'Received',
            'terminal' => false,
            'badge_class' => 's-badge-amber',
        ],
        'returned' => [
            'label' => 'Returned',
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
        'draft' => ['confirmed', 'cancelled'],
        'confirmed' => ['received', 'cancelled'],
        'received' => ['returned', 'cancelled'],
        'returned' => [],
        'cancelled' => [],
    ],

    'fallback_number_prefix' => 'VS-',

];
