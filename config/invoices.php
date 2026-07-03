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

    /*
    |--------------------------------------------------------------------------
    | Stored statuses eligible for overdue derivation
    |--------------------------------------------------------------------------
    |
    | Overdue is never persisted. These stored statuses may display as overdue
    | when due_at is in the past and amount_outstanding > 0.
    |
    */

    'overdue_eligible_stored_statuses' => ['issued', 'partially_paid'],

    /*
    |--------------------------------------------------------------------------
    | Effective status resolution (read-time)
    |--------------------------------------------------------------------------
    |
    | Rules are evaluated top-to-bottom. Each `when` clause ANDs field
    | predicates against (stored_status, amount_paid, total,
    | amount_outstanding, due_at, now). Overdue is a derived effective status
    | and is never written to the event stream.
    |
    */

    'status_resolution' => [
        [
            'when' => ['stored_status' => 'void'],
            'status' => 'void',
        ],
        [
            'when' => ['stored_status' => 'credited'],
            'status' => 'credited',
        ],
        [
            'when' => ['stored_status' => 'draft'],
            'status' => 'draft',
        ],
        [
            'when' => [
                'amount_outstanding' => ['lte' => 0],
                'amount_paid' => ['gt' => 0],
            ],
            'status' => 'paid',
        ],
        [
            'when' => [
                'amount_paid' => ['gt' => 0],
                'amount_outstanding' => ['gt' => 0],
                'due_at' => ['past' => true],
            ],
            'status' => 'overdue',
        ],
        [
            'when' => [
                'amount_paid' => ['gt' => 0],
                'amount_outstanding' => ['gt' => 0],
            ],
            'status' => 'partially_paid',
        ],
        [
            'when' => [
                'stored_status' => 'issued',
                'amount_outstanding' => ['gt' => 0],
                'due_at' => ['past' => true],
            ],
            'status' => 'overdue',
        ],
        [
            'when' => ['stored_status' => 'issued'],
            'status' => 'issued',
        ],
    ],

    'overdue_batch_size' => 500,

];
