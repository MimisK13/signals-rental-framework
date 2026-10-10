<?php

namespace Database\Factories;

use App\Enums\AssetLifecycleEventType;
use App\Enums\AssetLifecycleStatus;
use App\Models\Asset;
use App\Models\AssetLifecycleEvent;
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
