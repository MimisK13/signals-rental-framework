<?php

namespace App\Actions\Assets;

use App\Enums\AssetLifecycleEventType;
use App\Enums\AssetLifecycleStatus;
use App\Models\Asset;
use App\Services\Assets\AssetLifecycleTransitioner;
use Illuminate\Support\Facades\Gate;

class ReactivateAsset
{
    /** @param array<string, mixed> $attributes */
    public function __invoke(Asset $asset, array $attributes = []): void
    {
        Gate::authorize('stock.adjust');

        app(AssetLifecycleTransitioner::class)->transition($asset, AssetLifecycleEventType::Reactivated, AssetLifecycleStatus::Active, $attributes);
    }
}
