<?php

namespace App\Data\Pricing;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;

class UpdateSurchargeGroupData extends Data
{
    /**
     * @param  list<array<string, mixed>>|null  $surcharges
     */
    public function __construct(
        #[Max(255)]
        public ?string $name = null,
        public ?string $description = null,
        public ?bool $is_default = null,
        public ?bool $is_active = null,
        public ?int $sort_order = null,
        public ?array $surcharges = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return array_merge([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ], SurchargeCriteriaValidation::nestedRules());
    }
}
