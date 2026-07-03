<?php

use App\Enums\OpportunityState;

return [

    /*
    |--------------------------------------------------------------------------
    | Auditable event → notification type map
    |--------------------------------------------------------------------------
    |
    | Maps domain audit actions (the AuditableEvent `action` string) to one or
    | more notification type keys. Optional `when` clauses are evaluated as data
    | (field/old-new/model comparisons) — never a hardcoded status matrix in PHP.
    |
    | Each entry supports:
    |  - `type` (string) — a single notification type key, or
    |  - `types` (list<string>) — fan-out to multiple notification types, and
    |  - `when` (list<array>) — optional ANDed conditions (all must match).
    |
    | Condition shape: `source` (new|old|model|metadata), `field`, `operator`
    | (eq|neq|in|not_in|present|blank), `value` (when required).
    |
    | Plugins extend the map at runtime via EventNotificationMapRegistry::register().
    |
    */

    'event_map' => [
        'opportunity.created' => [
            ['type' => 'opportunity.created'],
        ],

        'opportunity.converted_to_order' => [
            ['type' => 'opportunity.confirmed'],
        ],

        'opportunity.status_changed' => [
            [
                'type' => 'opportunity.cancelled',
                'when' => [
                    ['source' => 'model', 'field' => 'state', 'operator' => 'eq', 'value' => OpportunityState::Order->value],
                    ['source' => 'new', 'field' => 'status', 'operator' => 'eq', 'value' => 6],
                ],
            ],
            [
                'type' => 'opportunity.cancelled',
                'when' => [
                    ['source' => 'model', 'field' => 'state', 'operator' => 'eq', 'value' => OpportunityState::Quotation->value],
                    ['source' => 'new', 'field' => 'status', 'operator' => 'in', 'value' => [2, 3]],
                ],
            ],
            [
                'type' => 'opportunity.on_hold',
                'when' => [
                    ['source' => 'model', 'field' => 'state', 'operator' => 'eq', 'value' => OpportunityState::Quotation->value],
                    ['source' => 'new', 'field' => 'status', 'operator' => 'eq', 'value' => 4],
                ],
            ],
            [
                'type' => 'opportunity.checked_out',
                'when' => [
                    ['source' => 'model', 'field' => 'state', 'operator' => 'eq', 'value' => OpportunityState::Order->value],
                    ['source' => 'new', 'field' => 'status', 'operator' => 'eq', 'value' => 1],
                ],
            ],
            [
                'type' => 'opportunity.checked_in',
                'when' => [
                    ['source' => 'model', 'field' => 'state', 'operator' => 'eq', 'value' => OpportunityState::Order->value],
                    ['source' => 'new', 'field' => 'status', 'operator' => 'eq', 'value' => 3],
                ],
            ],
        ],

        'opportunity.status_promoted' => [
            [
                'type' => 'opportunity.checked_out',
                'when' => [
                    ['source' => 'model', 'field' => 'state', 'operator' => 'eq', 'value' => OpportunityState::Order->value],
                    ['source' => 'new', 'field' => 'status', 'operator' => 'eq', 'value' => 1],
                ],
            ],
            [
                'type' => 'opportunity.checked_in',
                'when' => [
                    ['source' => 'model', 'field' => 'state', 'operator' => 'eq', 'value' => OpportunityState::Order->value],
                    ['source' => 'new', 'field' => 'status', 'operator' => 'eq', 'value' => 3],
                ],
            ],
        ],

        'shortage.waitlist.matched' => [
            ['type' => 'shortage.waitlist.matched'],
        ],

        'shortage.waitlist.created' => [
            ['type' => 'shortage.waitlist.created'],
        ],

        'shortage.resolution.confirmed' => [
            ['type' => 'shortage.resolution.confirmed'],
        ],
    ],

];
