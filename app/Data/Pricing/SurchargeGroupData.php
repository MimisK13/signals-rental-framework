<?php

namespace App\Data\Pricing;

use App\Models\SurchargeGroup;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class SurchargeGroupData extends Data
{
    /**
     * @param  list<SurchargeData>|null  $surcharges
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?string $description,
        public bool $is_default,
        public bool $is_active,
        public int $sort_order,
        public ?array $surcharges,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function fromModel(SurchargeGroup $group, bool $includeSurcharges = false): self
    {
        /** @var Carbon $createdAt */
        $createdAt = $group->created_at;

        /** @var Carbon $updatedAt */
        $updatedAt = $group->updated_at;

        $surcharges = null;

        if ($includeSurcharges && $group->relationLoaded('surcharges')) {
            $surcharges = $group->surcharges
                ->map(fn ($surcharge): SurchargeData => SurchargeData::fromModel($surcharge))
                ->all();
        }

        return new self(
            id: $group->id,
            name: $group->name,
            description: $group->description,
            is_default: $group->is_default,
            is_active: $group->is_active,
            sort_order: $group->sort_order,
            surcharges: $surcharges,
            created_at: $createdAt->toIso8601String(),
            updated_at: $updatedAt->toIso8601String(),
        );
    }
}
