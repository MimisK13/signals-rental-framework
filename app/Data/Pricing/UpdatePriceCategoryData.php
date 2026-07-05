<?php

namespace App\Data\Pricing;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Sometimes;
use Spatie\LaravelData\Data;

class UpdatePriceCategoryData extends Data
{
    /**
     * @param  list<array{tier_number: int, label?: string|null, multiplier: string, sort_order?: int}>|null  $tiers
     */
    public function __construct(
        #[Sometimes, Max(255)]
        public ?string $name = null,
        public ?string $description = null,
        public ?bool $is_active = null,
        public ?array $tiers = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'tiers' => ['sometimes', 'nullable', 'array'],
            'tiers.*.tier_number' => ['required', 'integer', 'min:1'],
            'tiers.*.label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'tiers.*.multiplier' => ['required', 'numeric', 'min:0'],
            'tiers.*.sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
