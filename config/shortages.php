<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Shortage resolver settings
    |--------------------------------------------------------------------------
    |
    | Per-resolver configuration. The sub-hire resolver defaults to manual-only
    | even when store shortage_auto_resolve_enabled is on — opt in via
    | shortages.resolvers.subhire.auto_executable.
    |
    */

    'resolvers' => [
        'subhire' => [
            'auto_executable' => env('SHORTAGE_SUBHIRE_AUTO_EXECUTABLE', false),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cost apportionment
    |--------------------------------------------------------------------------
    |
    | Default strategy for distributing virtual stock intake costs across
    | participating opportunities. Per-intake overrides live on
    | virtual_stock_intakes.cost_apportionment_strategy. Changing this setting
    | does not retroactively re-apportion existing intakes — only new triggers
    | pick up the new default.
    |
    */

    'cost_apportionment_strategy' => env('SHORTAGE_COST_APPORTIONMENT_STRATEGY', 'primary_job'),

];
