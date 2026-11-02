<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Actions;

use App\Concerns\CommitsVerbsEvents;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\Models\QuotationItem;
use App\Domain\Quotations\Models\QuotationVersion;
use App\Domain\Quotations\Services\QuotationAggregateTotals;
use App\Domain\Quotations\Services\QuotationEditingGuard;
use App\Domain\Quotations\Verbs\Events\QuotationItemRemoved;
use Illuminate\Support\Facades\Gate;

final class RemoveQuotationItem
{
    use CommitsVerbsEvents;

    public function __construct(
        private readonly QuotationEditingGuard $editingGuard,
        private readonly QuotationAggregateTotals $totals,
    ) {}

    public function __invoke(QuotationItem $item): void
    {
        $quotation = $item->quotation()->firstOrFail();
        Gate::authorize('update', $quotation);

        $this->commitVerbs(function () use ($quotation, $item): void {
            $lockedQuotation = Quotation::query()->lockForUpdate()->findOrFail($quotation->id);
            $lockedItem = QuotationItem::query()->lockForUpdate()->findOrFail($item->id);
            $lockedVersion = QuotationVersion::query()->lockForUpdate()->findOrFail($lockedItem->version_id);
            $this->editingGuard->assertEditable($lockedQuotation, $lockedVersion);
            $items = $lockedVersion->items()
                ->where(function ($query) use ($lockedItem): void {
                    $query->whereKey($lockedItem->id)->orWhere('path', 'like', $lockedItem->path.'%');
                })
                ->orderByDesc('path')
                ->lockForUpdate()
                ->get();
            $removedIds = $items->pluck('id')->map(fn ($id): int => (int) $id)->all();
            $totals = $this->totals->without($lockedVersion, $removedIds);

            foreach ($items as $removedItem) {
                QuotationItemRemoved::fire(
                    item_id: $removedItem->state_id,
                    quotation_id: $lockedQuotation->state_id,
                    version_id: $lockedVersion->state_id,
                    aggregate_net: $totals['net'],
                    aggregate_tax: $totals['tax'],
                    aggregate_gross: $totals['gross'],
                );
            }
        });
    }
}
