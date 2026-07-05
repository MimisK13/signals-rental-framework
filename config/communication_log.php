<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Communication log delivery statuses
    |--------------------------------------------------------------------------
    |
    | Status keys and metadata for outbound communication tracking. Transitions
    | are data-driven so plugins and future delivery webhooks can extend the
    | lifecycle without hardcoded match arms in application code.
    |
    */

    'statuses' => [
        'queued' => [
            'label' => 'Queued',
            'terminal' => false,
        ],
        'sent' => [
            'label' => 'Sent',
            'terminal' => false,
        ],
        'delivered' => [
            'label' => 'Delivered',
            'terminal' => false,
        ],
        'failed' => [
            'label' => 'Failed',
            'terminal' => true,
        ],
        'bounced' => [
            'label' => 'Bounced',
            'terminal' => true,
        ],
        'opened' => [
            'label' => 'Opened',
            'terminal' => false,
        ],
        'clicked' => [
            'label' => 'Clicked',
            'terminal' => false,
        ],
        'suppressed' => [
            'label' => 'Suppressed (opted out)',
            'terminal' => true,
        ],
    ],

    'transitions' => [
        'queued' => ['sent', 'failed', 'suppressed'],
        'sent' => ['delivered', 'failed', 'bounced', 'opened'],
        'delivered' => ['opened', 'clicked'],
        'opened' => ['clicked'],
        'failed' => [],
        'bounced' => [],
        'clicked' => [],
        'suppressed' => [],
    ],

];
