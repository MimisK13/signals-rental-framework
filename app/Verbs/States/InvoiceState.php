<?php

namespace App\Verbs\States;

use Carbon\CarbonImmutable;
use Thunk\Verbs\State;

/**
 * In-memory event-sourced representation of an invoice.
 *
 * Verbs folds events onto this object via their apply() methods. Business rules
 * live in events; projections live in their handle() methods.
 */
class InvoiceState extends State
{
    /** Application-allocated small projection PK (set by the genesis event). */
    public int $invoice_id = 0;

    public string $number = '';

    public string $type = 'standard';

    public bool $is_posting = true;

    public string $status = 'draft';

    public int $member_id = 0;

    public int $store_id = 0;

    public ?int $opportunity_id = null;

    public ?CarbonImmutable $issued_at = null;

    public ?CarbonImmutable $due_at = null;

    public ?CarbonImmutable $paid_at = null;

    public int $subtotal = 0;

    public int $tax_total = 0;

    public int $total = 0;

    public int $amount_paid = 0;

    public int $amount_outstanding = 0;

    public int $credited_total = 0;

    public string $currency_code = 'GBP';

    public string $exchange_rate = '1';

    public bool $prices_include_tax = false;

    public ?string $notes = null;

    public ?string $reference = null;

    /** @var list<string> */
    public array $tag_list = [];

    public bool $is_void = false;

    public bool $is_deleted = false;

    public bool $is_locked = false;

    public ?CarbonImmutable $voided_at = null;

    public ?CarbonImmutable $deleted_at = null;

    public ?CarbonImmutable $generated_at = null;

    public ?CarbonImmutable $last_event_at = null;
}
