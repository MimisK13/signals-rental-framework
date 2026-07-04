<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Supplier cost basis values
    |--------------------------------------------------------------------------
    |
    | Config-driven cost basis keys for product_supplier_costs.cost_basis.
    | Plugins may merge additional bases via the plugin registrar in future.
    |
    */

    'cost_bases' => [
        'per_unit' => [
            'label' => 'Per unit',
        ],
        'per_day' => [
            'label' => 'Per day',
        ],
        'per_week' => [
            'label' => 'Per week',
        ],
        'flat' => [
            'label' => 'Flat',
        ],
    ],

];
