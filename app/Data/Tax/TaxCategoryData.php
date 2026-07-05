<?php

namespace App\Data\Tax;

use App\Models\TaxCategory;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class TaxCategoryData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public string $type,
        public ?string $description,
        public bool $is_default,
        public bool $is_active,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function fromModel(TaxCategory $taxCategory): self
    {
        /** @var Carbon $createdAt */
        $createdAt = $taxCategory->created_at;

        /** @var Carbon $updatedAt */
        $updatedAt = $taxCategory->updated_at;

        return new self(
            id: $taxCategory->id,
            name: $taxCategory->name,
            slug: $taxCategory->slug,
            type: $taxCategory->type->value,
            description: $taxCategory->description,
            is_default: $taxCategory->is_default,
            is_active: $taxCategory->is_active,
            created_at: $createdAt->toIso8601String(),
            updated_at: $updatedAt->toIso8601String(),
        );
    }
}
