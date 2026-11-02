<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class UpdateQuotationItemData extends Data
{
    /** @param array<string, mixed>|null|Optional $custom_fields */
    public function __construct(
        public string|Optional $name = new Optional,
        public string|null|Optional $description = new Optional,
        public string|Optional $quantity = new Optional,
        public int|null|Optional $unit_price = new Optional,
        public string|null|Optional $discount_percent = new Optional,
        public string|null|Optional $starts_at = new Optional,
        public string|null|Optional $ends_at = new Optional,
        public bool|Optional $is_optional = new Optional,
        public array|null|Optional $custom_fields = new Optional,
        public string|null|Optional $notes = new Optional,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'quantity' => ['sometimes', 'numeric', 'gt:0'],
            'unit_price' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'discount_percent' => ['sometimes', 'nullable', 'numeric', 'between:0,100'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
            'is_optional' => ['sometimes', 'boolean'],
            'custom_fields' => ['sometimes', 'nullable', 'array'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
