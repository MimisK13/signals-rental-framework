<?php

namespace App\Verbs\States;

use Carbon\CarbonImmutable;
use Thunk\Verbs\State;

/**
 * In-memory event-sourced representation of a virtual stock intake.
 */
class VirtualStockIntakeState extends State
{
    public int $virtual_stock_intake_id = 0;

    public string $number = '';

    public string $status = 'draft';

    public ?int $purchase_order_id = null;

    public ?int $purchase_order_item_id = null;

    public int $product_id = 0;

    public int $store_id = 0;

    public int $quantity = 0;

    public ?CarbonImmutable $available_from = null;

    public ?CarbonImmutable $available_until = null;

    public ?CarbonImmutable $confirmed_at = null;

    public ?CarbonImmutable $received_at = null;

    public ?CarbonImmutable $returned_at = null;

    public ?CarbonImmutable $cancelled_at = null;

    public int $unit_cost = 0;

    public string $currency_code = 'GBP';

    public ?string $notes = null;

    public ?int $created_by = null;

    /** Application-allocated demand row PK created on confirm. */
    public ?int $demand_id = null;

    public bool $is_deleted = false;

    public ?CarbonImmutable $deleted_at = null;

    public ?CarbonImmutable $created_at = null;

    public ?CarbonImmutable $last_event_at = null;
}
