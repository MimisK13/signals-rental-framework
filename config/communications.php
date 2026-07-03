<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Customer communication unsubscribe links
    |--------------------------------------------------------------------------
    |
    | Signed URLs for one-click opt-out from marketing/transactional emails.
    | Long-but-finite expiry satisfies CAN-SPAM while limiting link lifetime.
    |
    */

    'unsubscribe_url_ttl_days' => 365,

    /*
    |--------------------------------------------------------------------------
    | Public unsubscribe route throttling
    |--------------------------------------------------------------------------
    |
    | Max attempts per minute per IP for GET/POST unsubscribe endpoints.
    |
    */

    'unsubscribe_throttle' => '10,1',

];
