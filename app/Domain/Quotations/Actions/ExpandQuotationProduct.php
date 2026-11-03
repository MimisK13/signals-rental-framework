<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Actions;

use App\Domain\Products\Models\Product;
use App\Domain\Quotations\Data\AddQuotationItemData;
use App\Domain\Quotations\Enums\QuotationItemType;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\Models\QuotationItem;
use App\Domain\Quotations\Models\QuotationVersion;
use App\Enums\LineItemTransactionType;
use Illuminate\Support\Facades\DB;

final readonly class ExpandQuotationProduct
{
    public function __construct(private AddQuotationItem $addItem) {}

    /** @return list<int> */
    public function __invoke(Quotation $quotation, QuotationVersion $version, QuotationItem $principal): array
    {
        $product = Product::query()
            ->with(['components.componentProduct', 'accessories' => fn ($query) => $query->where('included', true)->with('accessoryProduct')])
            ->findOrFail($principal->itemable_id);

        return DB::transaction(function () use ($quotation, $version, $principal, $product): array {
            $created = [];
            $seen = [];

            foreach ($product->components as $component) {
                $linked = $component->componentProduct;
                if ($linked === null || isset($seen[$linked->id])) {
                    continue;
                }
                $seen[$linked->id] = true;
                $created[] = ($this->addItem)($quotation, $version, AddQuotationItemData::from([
                    'name' => $linked->name,
                    'itemable_id' => $linked->id,
                    'itemable_type' => Product::class,
                    'item_type' => QuotationItemType::Accessory->value,
                    'quantity' => bcmul((string) $principal->quantity, (string) $component->quantity, 2),
                    'transaction_type' => LineItemTransactionType::Rental->value,
                    'parent_path' => $principal->path,
                ]))->id;
            }

            foreach ($product->accessories as $accessory) {
                $linked = $accessory->accessoryProduct;
                if ($linked === null || isset($seen[$linked->id])) {
                    continue;
                }
                $seen[$linked->id] = true;
                $created[] = ($this->addItem)($quotation, $version, AddQuotationItemData::from([
                    'name' => $linked->name,
                    'itemable_id' => $linked->id,
                    'itemable_type' => Product::class,
                    'item_type' => QuotationItemType::Accessory->value,
                    'quantity' => bcmul((string) $principal->quantity, (string) $accessory->quantity, 2),
                    'transaction_type' => LineItemTransactionType::Rental->value,
                    'parent_path' => $principal->path,
                    'unit_price' => $accessory->zero_priced ? 0 : null,
                ]))->id;
            }

            return $created;
        });
    }
}
