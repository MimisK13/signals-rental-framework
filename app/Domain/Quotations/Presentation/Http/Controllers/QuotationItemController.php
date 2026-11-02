<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Presentation\Http\Controllers;

use App\Domain\Quotations\Actions\RemoveQuotationItem;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\Models\QuotationItem;
use Illuminate\Http\Response;

final class QuotationItemController
{
    public function destroy(Quotation $quotation, QuotationItem $item): Response
    {
        abort_unless($item->quotation_id === $quotation->id, 404);
        app(RemoveQuotationItem::class)($item);

        return response()->noContent();
    }
}
