<?php

namespace App\Data\Tax;

use App\Models\TaxZone;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class TaxZoneData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public ?string $description,
        public int $priority,
        public bool $is_active,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function fromModel(TaxZone $taxZone): self
    {
        /** @var Carbon $createdAt */
        $createdAt = $taxZone->created_at;

        /** @var Carbon $updatedAt */
        $updatedAt = $taxZone->updated_at;

        return new self(
            id: $taxZone->id,
            name: $taxZone->name,
            slug: $taxZone->slug,
            description: $taxZone->description,
            priority: $taxZone->priority,
            is_active: $taxZone->is_active,
            created_at: $createdAt->toIso8601String(),
            updated_at: $updatedAt->toIso8601String(),
        );
    }
}
