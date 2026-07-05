<?php

return [

    /*
    |--------------------------------------------------------------------------
    | VIES REST API endpoint
    |--------------------------------------------------------------------------
    |
    | EU VAT number validation via the European Commission VIES REST service.
    | Overridable in tests via config() — never call live VIES in the suite.
    |
    */

    'vies_rest_url' => env('TAX_VIES_REST_URL', 'https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number'),

];
