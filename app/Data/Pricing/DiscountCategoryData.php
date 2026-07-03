<?php

namespace App\Data\Pricing;

use App\Models\DiscountCategory;
use App\Models\DiscountRule;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;

class DiscountCategoryData extends Data
{
    /**
     * @param  Lazy|list<array<string, mixed>>  $rules
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?string $description,
        public bool $is_active,
        public Lazy|array $rules,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function fromModel(DiscountCategory $category): self
    {
        /** @var Carbon $createdAt */
        $createdAt = $category->created_at;

        /** @var Carbon $updatedAt */
        $updatedAt = $category->updated_at;

        return new self(
            id: $category->id,
            name: $category->name,
            description: $category->description,
            is_active: $category->is_active,
            rules: Lazy::whenLoaded(
                'rules',
                $category,
                fn (): array => $category->rules
                    ->map(fn (DiscountRule $rule): array => DiscountRuleData::fromModel($rule)->toArray())
                    ->all(),
            ),
            created_at: $createdAt->toIso8601String(),
            updated_at: $updatedAt->toIso8601String(),
        );
    }
}
