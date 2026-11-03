<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Actions;

use App\Domain\Products\Enums\ProductType;
use App\Domain\Products\Models\Product;
use App\Domain\Quotations\Data\AddQuotationItemData;
use App\Domain\Quotations\Enums\QuotationItemType;
use App\Domain\Quotations\Models\QuotationItem;
use App\Enums\LineItemTransactionType;
use Illuminate\Support\Facades\DB;

final readonly class SubstituteQuotationItem
{
    public function __construct(
        private RemoveQuotationItem $removeItem,
        private AddQuotationItem $addItem,
        private ExpandQuotationProduct $expandProduct,
    ) {}

    public function __invoke(QuotationItem $item, Product $replacement): QuotationItem
    {
        $quotation = $item->quotation()->firstOrFail();
        $version = $item->version()->firstOrFail();
        $parentPath = $item->parentPath();
        $quantity = (string) $item->quantity;

        return DB::transaction(function () use ($item, $replacement, $quotation, $version, $parentPath, $quantity): QuotationItem {
            ($this->removeItem)($item);
            $itemType = $replacement->product_type === ProductType::Service
                ? QuotationItemType::Service
                : QuotationItemType::Product;
            $transactionType = match ($replacement->product_type) {
                ProductType::Sale => LineItemTransactionType::Sale,
                ProductType::Service => LineItemTransactionType::Service,
                default => LineItemTransactionType::Rental,
            };
            $created = ($this->addItem)($quotation->refresh(), $version->refresh(), AddQuotationItemData::from([
                'name' => $replacement->name,
                'itemable_id' => $replacement->id,
                'itemable_type' => Product::class,
                'item_type' => $itemType->value,
                'quantity' => $quantity,
                'transaction_type' => $transactionType->value,
                'parent_path' => $parentPath,
            ]));
            $substitute = QuotationItem::query()->findOrFail($created->id);

            if ($replacement->components()->exists() || $replacement->accessories()->where('included', true)->exists()) {
                ($this->expandProduct)($quotation->refresh(), $version->refresh(), $substitute);
            }

            return $substitute;
        });
    }
}
