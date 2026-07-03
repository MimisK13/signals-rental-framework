<?php

return [

    /*
    |--------------------------------------------------------------------------
    | PDF Rendering Driver
    |--------------------------------------------------------------------------
    |
    | The default PDF driver for generated documents. The open-source core ships
    | with DomPDF; alternative drivers (Browsershot, Cloudflare Browser Rendering)
    | can be registered by plugins or the commercial Cloud package.
    |
    */

    'pdf_driver' => env('DOCUMENTS_PDF_DRIVER', 'dompdf'),

    /*
    |--------------------------------------------------------------------------
    | Default Page Layout
    |--------------------------------------------------------------------------
    |
    | Applied when a template does not specify its own page configuration.
    | Individual templates may override these via the `page_config` JSON column.
    |
    */

    'defaults' => [
        'page_size' => 'A4',
        'page_orientation' => 'portrait',
        'locale' => 'en',
    ],

    /*
    |--------------------------------------------------------------------------
    | Public Sharing
    |--------------------------------------------------------------------------
    |
    | Length of the opaque token issued for public document links. Tokens are
    | stored on the documents table and looked up directly (not signed URLs).
    |
    */

    'public_token_length' => (int) env('DOCUMENTS_PUBLIC_TOKEN_LENGTH', 64),

];
