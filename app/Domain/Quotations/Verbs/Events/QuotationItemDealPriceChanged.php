<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Verbs\Events;

use App\Domain\Quotations\Models\QuotationItem;
use App\Domain\Quotations\Verbs\States\QuotationItemState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

final class QuotationItemDealPriceChanged extends Event
{
    public function __construct(
        #[StateId(QuotationItemState::class)] public int $item_id,
        public ?string $deal_price_discount_percent,
        public ?int $deal_price,
        public ?int $deal_price_original_total,
        public ?int $deal_price_original_tax_total,
        public ?int $deal_price_original_gross_total,
        public int $total,
        public int $tax_total,
        public int $gross_total,
    ) {}

    public function apply(QuotationItemState $state): void
    {
        $state->deal_price_discount_percent = $this->deal_price_discount_percent;
        $state->deal_price = $this->deal_price;
        $state->deal_price_original_total = $this->deal_price_original_total;
        $state->deal_price_original_tax_total = $this->deal_price_original_tax_total;
        $state->deal_price_original_gross_total = $this->deal_price_original_gross_total;
        $state->total = $this->total;
        $state->tax_total = $this->tax_total;
        $state->gross_total = $this->gross_total;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(QuotationItemState $state): void
    {
        QuotationItem::query()->where('state_id', $state->id)->update([
            'deal_price_discount_percent' => $state->deal_price_discount_percent,
            'deal_price' => $state->deal_price,
            'deal_price_original_total' => $state->deal_price_original_total,
            'deal_price_original_tax_total' => $state->deal_price_original_tax_total,
            'deal_price_original_gross_total' => $state->deal_price_original_gross_total,
            'total' => $state->total,
            'tax_total' => $state->tax_total,
            'gross_total' => $state->gross_total,
        ]);
    }
}
