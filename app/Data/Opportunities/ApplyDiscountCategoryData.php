<?php

namespace App\Data\Opportunities;

use Spatie\LaravelData\Data;

/**
 * Input DTO for applying (or re-applying) an organisation discount category to
 * opportunity line items.
 */
class ApplyDiscountCategoryData extends Data
{
    /**
     * @param  list<int>|null  $opportunity_item_ids  Null/empty = all eligible lines.
     */
    public function __construct(
        public ?array $opportunity_item_ids = null,
        public bool $reset_manual = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'opportunity_item_ids' => ['sometimes', 'nullable', 'array'],
            'opportunity_item_ids.*' => ['integer'],
            'reset_manual' => ['sometimes', 'boolean'],
        ];
    }
}
