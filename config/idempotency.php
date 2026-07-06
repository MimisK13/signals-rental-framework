<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Idempotency key retention
    |--------------------------------------------------------------------------
    |
    | Stored replay payloads for checkout and payment endpoints expire after
    | ttl_hours. Rows past expires_at are pruned by the scheduled model:prune
    | command via ApiIdempotencyKey::prunable().
    |
    */

    'ttl_hours' => (int) env('API_IDEMPOTENCY_TTL_HOURS', 24),

];
