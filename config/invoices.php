<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Invoice lifecycle statuses
    |--------------------------------------------------------------------------
    |
    | Status keys and metadata for the invoicing stream. Transitions are
    | data-driven so plugins and future lifecycle actions can extend the
    | workflow without hardcoded match arms in application code.
    |
    */

    'default_status' => 'draft',

    'statuses' => [
        'draft' => [
            'label' => 'Draft',
            'terminal' => false,
        ],
        'issued' => [
            'label' => 'Issued',
            'terminal' => false,
        ],
        'partially_paid' => [
            'label' => 'Partially paid',
            'terminal' => false,
        ],
        'paid' => [
            'label' => 'Paid',
            'terminal' => true,
        ],
        'overdue' => [
            'label' => 'Overdue',
            'terminal' => false,
        ],
        'void' => [
            'label' => 'Void',
            'terminal' => true,
        ],
        'credited' => [
            'label' => 'Credited',
            'terminal' => true,
        ],
    ],

    'transitions' => [
        'draft' => ['issued', 'void'],
        'issued' => ['partially_paid', 'paid', 'overdue', 'void', 'credited'],
        'partially_paid' => ['paid', 'overdue', 'void', 'credited'],
        'overdue' => ['partially_paid', 'paid', 'void', 'credited'],
        'paid' => ['credited'],
        'void' => [],
        'credited' => [],
    ],

];
