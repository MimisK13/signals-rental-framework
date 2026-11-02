<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Data;

use App\Domain\Quotations\Models\QuotationItem;
use Spatie\LaravelData\Data;

final class QuotationItemData extends Data
{
    public function __construct(
        public int $id,
        public int $quotation_id,
        public int $version_id,
        public string $item_type,
        public string $path,
        public string $name,
        public string $quantity,
        public int $unit_price,
        public int $total,
        public int $tax_total,
        public int $gross_total,
        public bool $is_optional,
    ) {}

    public static function fromModel(QuotationItem $item): self
    {
        return new self(
            id: $item->id,
            quotation_id: $item->quotation_id,
            version_id: $item->version_id,
            item_type: $item->item_type->value,
            path: $item->path,
            name: $item->name,
            quantity: $item->quantity,
            unit_price: $item->unit_price,
            total: $item->total,
            tax_total: $item->tax_total,
            gross_total: $item->gross_total,
            is_optional: $item->is_optional,
        );
    }
}
