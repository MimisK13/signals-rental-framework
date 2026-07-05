<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Credit note lifecycle statuses
    |--------------------------------------------------------------------------
    |
    | Status keys and metadata for credit note projections. P34 issues credit
    | notes directly as "issued"; application to other invoices lands later.
    |
    */

    'default_status' => 'issued',

    'statuses' => [
        'draft' => [
            'label' => 'Draft',
            'terminal' => false,
        ],
        'issued' => [
            'label' => 'Issued',
            'terminal' => false,
        ],
        'applied' => [
            'label' => 'Applied',
            'terminal' => true,
        ],
    ],

    'transitions' => [
        'draft' => ['issued'],
        'issued' => ['applied'],
        'applied' => [],
    ],

    'fallback_number_prefix' => 'CN-',

];
