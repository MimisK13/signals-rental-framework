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

    /*
    |--------------------------------------------------------------------------
    | Virtual serialised asset lifecycle statuses
    |--------------------------------------------------------------------------
    |
    | Config-driven statuses for stock_levels rows where is_virtual = true.
    | Operational assignment stages mirror owned assets via opportunity_item_assets;
    | these statuses track the virtual asset's supply lifecycle only.
    |
    */

    'virtual_asset_default_status' => 'available',

    'virtual_asset_statuses' => [
        'available' => [
            'label' => 'Available',
            'terminal' => false,
            'supplies_availability' => true,
            'badge_class' => 's-badge-green',
        ],
        'allocated' => [
            'label' => 'Allocated',
            'terminal' => false,
            'supplies_availability' => true,
            'badge_class' => 's-badge-amber',
        ],
        'retired' => [
            'label' => 'Retired',
            'terminal' => true,
            'supplies_availability' => false,
            'badge_class' => 's-badge-zinc',
        ],
    ],

    'virtual_asset_transitions' => [
        'available' => ['allocated', 'retired'],
        'allocated' => ['available', 'retired'],
        'retired' => [],
    ],

];
