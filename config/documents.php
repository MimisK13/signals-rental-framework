<?php

use App\Services\Documents\Drivers\BrowsershotDriver;
use App\Services\Documents\Drivers\DompdfDriver;

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
    | PDF Drivers
    |--------------------------------------------------------------------------
    */

    'drivers' => [
        'dompdf' => DompdfDriver::class,
        'browsershot' => BrowsershotDriver::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Sandboxed Blade Cache
    |--------------------------------------------------------------------------
    |
    | Dedicated cache path for compiled document templates, isolated from the
    | main application view cache for security and invalidation control.
    |
    */

    'sandbox_cache_path' => storage_path('framework/views/documents'),

    /*
    |--------------------------------------------------------------------------
    | Sandboxed Loop Iteration Cap
    |--------------------------------------------------------------------------
    |
    | Maximum number of rows a @foreach / @forelse loop may iterate during a
    | single template render. Arrays are silently truncated; other countable
    | iterables that exceed this limit raise a user-facing render error.
    |
    */

    'max_loop_iterations' => (int) env('DOCUMENTS_MAX_LOOP_ITERATIONS', 500),

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
        'page' => [
            'page_size' => 'A4',
            'page_orientation' => 'portrait',
            'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
        ],
        'branding' => [
            'primary_colour' => '#1f2937',
            'secondary_colour' => '#6b7280',
            'font_family' => 'Inter, system-ui, sans-serif',
        ],
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

    /*
    |--------------------------------------------------------------------------
    | Public Route Prefix
    |--------------------------------------------------------------------------
    |
    | Unauthenticated public document links are served at /{prefix}/{token}.
    |
    */

    'public_route_prefix' => env('DOCUMENTS_PUBLIC_ROUTE_PREFIX', 'd'),

    /*
    |--------------------------------------------------------------------------
    | Public Link PIN Protection
    |--------------------------------------------------------------------------
    */

    'public_pin_max_attempts' => (int) env('DOCUMENTS_PUBLIC_PIN_MAX_ATTEMPTS', 5),

    'public_pin_decay_seconds' => (int) env('DOCUMENTS_PUBLIC_PIN_DECAY_SECONDS', 900),

];
