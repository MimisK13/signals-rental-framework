<?php

namespace App\Data\Pricing;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class CreateSurchargeGroupData extends Data
{
    /**
     * @param  list<array<string, mixed>>  $surcharges
     */
    public function __construct(
        #[Required, Max(255)]
        public string $name,
        public ?string $description = null,
        public bool $is_default = false,
        public bool $is_active = true,
        public int $sort_order = 0,
        public array $surcharges = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return array_merge([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ], SurchargeCriteriaValidation::nestedRules());
    }
}
