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

];
