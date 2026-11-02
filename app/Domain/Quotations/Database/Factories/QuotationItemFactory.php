<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Database\Factories;

use App\Domain\Quotations\Enums\QuotationItemType;
use App\Domain\Quotations\Models\QuotationItem;
use App\Domain\Quotations\Models\QuotationVersion;
use App\Enums\ChargePeriod;
use App\Enums\LineItemTransactionType;
use App\Services\SequenceAllocator;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QuotationItem> */
final class QuotationItemFactory extends Factory
{
    protected $model = QuotationItem::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 5);
        $unitPrice = fake()->numberBetween(1000, 50000);

        return [
            'id' => app(SequenceAllocator::class)->next('quotation_items'),
            'state_id' => snowflake_id(),
            'version_id' => QuotationVersionFactory::new(),
            'quotation_id' => function (array $attributes): int {
                $quotationId = QuotationVersion::query()
                    ->whereKey($attributes['version_id'])
                    ->value('quotation_id');

                if (! is_int($quotationId)) {
                    throw new \RuntimeException('The quotation version has no quotation.');
                }

                return $quotationId;
            },
            'itemable_id' => null,
            'itemable_type' => null,
            'item_type' => QuotationItemType::Product,
            'path' => '0001',
            'name' => fake()->words(3, true),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'charge_period' => ChargePeriod::Day,
            'total' => $quantity * $unitPrice,
            'tax_total' => 0,
            'gross_total' => $quantity * $unitPrice,
            'currency_code' => 'EUR',
            'is_discount_manual' => false,
            'transaction_type' => LineItemTransactionType::Rental,
            'is_optional' => false,
        ];
    }
}
