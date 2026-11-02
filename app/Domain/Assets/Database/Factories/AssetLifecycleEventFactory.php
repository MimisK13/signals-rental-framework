<?php

declare(strict_types=1);

namespace App\Domain\Assets\Database\Factories;

use App\Domain\Assets\Enums\AssetLifecycleEventType;
use App\Domain\Assets\Enums\AssetLifecycleStatus;
use App\Domain\Assets\Models\Asset;
use App\Domain\Assets\Models\AssetLifecycleEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AssetLifecycleEvent> */
class AssetLifecycleEventFactory extends Factory
{
    protected $model = AssetLifecycleEvent::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'event_type' => AssetLifecycleEventType::MarkedMissing,
            'occurred_at' => now(),
            'previous_status' => AssetLifecycleStatus::Active,
            'new_status' => AssetLifecycleStatus::Missing,
        ];
    }
}
