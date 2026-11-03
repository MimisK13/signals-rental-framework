<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Data;

use App\Domain\Pricing\Enums\ChargePeriod;
use App\Domain\Pricing\Enums\LineItemTransactionType;
use App\Domain\Quotations\Enums\QuotationItemType;
use Spatie\LaravelData\Data;

final class AddQuotationItemData extends Data
{
    /** @param array<string, mixed>|null $custom_fields */
    public function __construct(
        public string $name,
        public string $item_type = 'product',
        public ?int $itemable_id = null,
        public ?string $itemable_type = null,
        public string $quantity = '1.00',
        public ?int $unit_price = null,
        public int $transaction_type = 0,
        public int $charge_period = 1,
        public ?string $parent_path = null,
        public ?int $revenue_group_id = null,
        public ?string $description = null,
        public ?string $discount_percent = null,
        public ?string $starts_at = null,
        public ?string $ends_at = null,
        public bool $is_optional = false,
        public ?array $custom_fields = null,
        public ?string $notes = null,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'item_type' => ['sometimes', 'string', 'in:'.implode(',', array_column(QuotationItemType::cases(), 'value'))],
            'itemable_id' => ['sometimes', 'nullable', 'integer'],
            'itemable_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'quantity' => ['sometimes', 'numeric', 'gt:0'],
            'unit_price' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'transaction_type' => ['sometimes', 'integer', 'in:'.implode(',', array_column(LineItemTransactionType::cases(), 'value'))],
            'charge_period' => ['sometimes', 'integer', 'in:'.implode(',', array_column(ChargePeriod::cases(), 'value'))],
            'parent_path' => ['sometimes', 'nullable', 'string', 'max:255'],
            'revenue_group_id' => ['sometimes', 'nullable', 'integer', 'exists:revenue_groups,id'],
            'description' => ['sometimes', 'nullable', 'string'],
            'discount_percent' => ['sometimes', 'nullable', 'numeric', 'between:0,100'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
            'is_optional' => ['sometimes', 'boolean'],
            'custom_fields' => ['sometimes', 'nullable', 'array'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
