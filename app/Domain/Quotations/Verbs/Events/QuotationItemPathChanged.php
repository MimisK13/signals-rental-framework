<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Verbs\Events;

use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\Models\QuotationItem;
use App\Domain\Quotations\Verbs\States\QuotationItemState;
use App\Domain\Quotations\Verbs\States\QuotationState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

final class QuotationItemPathChanged extends Event
{
    public function __construct(
        #[StateId(QuotationItemState::class)] public int $item_id,
        #[StateId(QuotationState::class)] public int $quotation_id,
        public string $path,
    ) {}

    public function applyToItem(QuotationItemState $state): void
    {
        $state->path = $this->path;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function applyToQuotation(QuotationState $state): void
    {
        $state->editor_revision++;
    }

    public function handle(QuotationItemState $itemState, QuotationState $quotationState): void
    {
        QuotationItem::query()->where('state_id', $itemState->id)->update(['path' => $itemState->path]);
        Quotation::query()->where('state_id', $quotationState->id)->update(['editor_revision' => $quotationState->editor_revision]);
    }
}
