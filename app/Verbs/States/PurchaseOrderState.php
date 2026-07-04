<?php

namespace App\Verbs\States;

use Carbon\CarbonImmutable;
use Thunk\Verbs\State;

/**
 * In-memory event-sourced representation of a purchase order.
 */
class PurchaseOrderState extends State
{
    /** Application-allocated small projection PK (set by the genesis event). */
    public int $purchase_order_id = 0;

    public string $number = '';

    public string $po_type = 'purchase';

    public string $status = 'draft';

    public int $supplier_id = 0;

    public int $store_id = 0;

    public ?int $opportunity_id = null;

    public ?int $shortage_resolution_id = null;

    public int $subtotal = 0;

    public int $tax_total = 0;

    public int $total = 0;

    public int $rounding_adjustment = 0;

    public string $currency_code = 'GBP';

    public string $exchange_rate = '1';

    public ?CarbonImmutable $expected_at = null;

    public ?CarbonImmutable $starts_at = null;

    public ?CarbonImmutable $ends_at = null;

    public ?string $reference = null;

    public ?string $notes = null;

    public ?string $supplier_reference = null;

    public ?CarbonImmutable $sent_at = null;

    public ?CarbonImmutable $received_at = null;

    public ?CarbonImmutable $cancelled_at = null;

    public ?int $created_by = null;

    public bool $is_deleted = false;

    public ?CarbonImmutable $deleted_at = null;

    public ?CarbonImmutable $created_at = null;

    public ?CarbonImmutable $last_event_at = null;

    /**
     * Cumulative received quantity per purchase_order_item_id (decimal strings).
     *
     * @var array<int, string>
     */
    public array $item_received_quantities = [];
}
