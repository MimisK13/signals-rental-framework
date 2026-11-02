<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Verbs\States;

use Carbon\CarbonImmutable;
use Thunk\Verbs\State;

final class QuotationItemState extends State
{
    public int $item_id = 0;

    public int $quotation_id = 0;

    public int $version_id = 0;

    public ?int $itemable_id = null;

    public ?string $itemable_type = null;

    public string $item_type = 'product';

    public string $path = '';

    public ?int $revenue_group_id = null;

    public string $name = '';

    public ?string $description = null;

    public string $quantity = '0.00';

    public int $unit_price = 0;

    public ?int $rate_definition_id = null;

    public int $charge_period = 1;

    public int $total = 0;

    public int $tax_total = 0;

    public int $gross_total = 0;

    public ?string $currency_code = null;

    public ?string $discount_percent = null;

    public ?string $deal_price_discount_percent = null;

    public ?int $deal_price = null;

    public ?int $deal_price_original_total = null;

    public ?int $deal_price_original_tax_total = null;

    public ?int $deal_price_original_gross_total = null;

    public bool $is_discount_manual = false;

    public ?string $tax_rate = null;

    public int $transaction_type = 0;

    public ?CarbonImmutable $starts_at = null;

    public ?CarbonImmutable $ends_at = null;

    public bool $is_optional = false;

    /** @var array<string, mixed>|null */
    public ?array $custom_fields = null;

    /** @var array<string, mixed>|null */
    public ?array $pricing_breakdown = null;

    public ?string $notes = null;

    public bool $is_deleted = false;

    public ?CarbonImmutable $last_event_at = null;
}
