<?php

namespace App\Data\Pricing;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class CreateDiscountCategoryData extends Data
{
    /**
     * @param  list<array{
     *     discount_percent: string|float|int,
     *     criteria?: array<string, mixed>|null,
     *     valid_from?: string|null,
     *     valid_to?: string|null,
     *     sort_order?: int
     * }>  $rules
     */
    public function __construct(
        #[Required, Max(255)]
        public string $name,
        public ?string $description = null,
        public bool $is_active = true,
        public array $rules = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return array_merge([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ], DiscountRuleCriteriaValidation::nestedRules());
    }
}
