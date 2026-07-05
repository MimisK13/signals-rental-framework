<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API Metering — Request Logging
    |--------------------------------------------------------------------------
    |
    | Controls inbound API request detail capture and retention. Detail rows
    | in request_logs are pruned after detail_retention_days; permanent usage
    | rollups live in usage_daily (see flush pipeline in later phases).
    |
    */

    'logging_enabled' => (bool) env('API_METERING_LOGGING_ENABLED', true),

    'capture_bodies' => (bool) env('API_METERING_CAPTURE_BODIES', false),

    'body_max_bytes' => (int) env('API_METERING_BODY_MAX_BYTES', 4096),

    'detail_retention_days' => (int) env('API_METERING_DETAIL_RETENTION_DAYS', 90),

    'buffered' => (bool) env('API_METERING_BUFFERED', false),

    'buffer_key' => env('API_METERING_BUFFER_KEY', 'api-metering:buffer'),

    'log_channel' => env('API_METERING_LOG_CHANNEL'),

    /*
    |--------------------------------------------------------------------------
    | Redaction
    |--------------------------------------------------------------------------
    |
    | Header and body field names to mask before persisting request/response
    | detail. Later phases reuse observability masking helpers where possible.
    |
    */

    'redaction' => [
        'Authorization',
        'X-Signals-Signature',
        'password',
        'secret',
        'token',
    ],

];
