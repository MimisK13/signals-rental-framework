<?php

namespace App\Data\Pricing;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Sometimes;
use Spatie\LaravelData\Data;

class UpdateDiscountCategoryData extends Data
{
    /**
     * @param  list<array{
     *     discount_percent: string|float|int,
     *     criteria?: array<string, mixed>|null,
     *     valid_from?: string|null,
     *     valid_to?: string|null,
     *     sort_order?: int
     * }>|null  $rules
     */
    public function __construct(
        #[Sometimes, Max(255)]
        public ?string $name = null,
        public ?string $description = null,
        public ?bool $is_active = null,
        public ?array $rules = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return array_merge([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ], DiscountRuleCriteriaValidation::nestedRules());
    }
}
