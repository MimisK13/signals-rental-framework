<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Actions;

use App\Domain\Quotations\Data\UpdateQuotationItemData;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\Models\QuotationItem;
use App\Domain\Quotations\Models\QuotationVersion;
use App\Domain\Quotations\Services\QuotationEditingGuard;
use Illuminate\Support\Facades\Gate;

final readonly class RecalculateQuotationVersion
{
    public function __construct(
        private QuotationEditingGuard $editingGuard,
        private UpdateQuotationItem $updateItem,
    ) {}

    public function __invoke(Quotation $quotation, QuotationVersion $version): QuotationVersion
    {
        Gate::authorize('update', $quotation);
        $this->editingGuard->assertEditable($quotation, $version);

        $version->items()->orderBy('path')->each(function (QuotationItem $item): void {
            ($this->updateItem)($item, UpdateQuotationItemData::from([]));
        });

        return $version->refresh();
    }
}
