<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Actions\Concerns;

use App\Domain\Quotations\Enums\QuotationItemType;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\Models\QuotationVersion;
use Illuminate\Validation\ValidationException;

trait AssertsQuotationCompleteness
{
    private function assertReadyForCustomer(Quotation $quotation, QuotationVersion $version): void
    {
        $errors = [];

        if ($quotation->starts_at === null) {
            $errors['starts_at'] = 'Set the quotation start before sending or accepting it.';
        }

        if ($quotation->ends_at === null) {
            $errors['ends_at'] = 'Set the quotation end before sending or accepting it.';
        }

        $hasPriceableItem = $version->items()
            ->where('item_type', '!=', QuotationItemType::Group->value)
            ->exists();

        if (! $hasPriceableItem) {
            $errors['items'] = 'Add at least one priceable item before sending or accepting the quotation.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
