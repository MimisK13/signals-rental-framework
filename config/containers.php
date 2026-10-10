<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default nesting depth ceiling
    |--------------------------------------------------------------------------
    |
    | Used when a container has no backing product (temporary containers) or
    | when rolling up nested contents for compliance manifests. Product-backed
    | containers prefer {@see Product::$container_max_nesting_depth}.
    |
    */

    'default_max_nesting_depth' => (int) env('CONTAINERS_DEFAULT_MAX_NESTING_DEPTH', 2),

    /*
    |--------------------------------------------------------------------------
    | Unseal reasons
    |--------------------------------------------------------------------------
    |
    | Config-driven allow-list for POST /api/v1/containers/{uuid}/unseal.
    | `transfer` is reserved for the transfer workflow.
    |
    | @var list<string>
    */

    'unseal_reasons' => [
        'manual',
        'transfer',
    ],

    /*
    |--------------------------------------------------------------------------
    | Dissolve reasons
    |--------------------------------------------------------------------------
    |
    | Config-driven allow-list for manual / dispatch dissolution audit fields.
    |
    | @var list<string>
    */

    'dissolve_reasons' => [
        'dispatched',
        'manual',
    ],

];
