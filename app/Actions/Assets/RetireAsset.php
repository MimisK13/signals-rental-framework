<?php

namespace App\Actions\Assets;

use App\Enums\AssetLifecycleEventType;
use App\Enums\AssetLifecycleStatus;
use App\Enums\AssetRetirementDisposition;
use App\Models\Asset;
use App\Services\Assets\AssetLifecycleTransitioner;
use Illuminate\Support\Facades\Gate;

class RetireAsset
{
    /** @param array<string, mixed> $attributes */
    public function __invoke(Asset $asset, array $attributes = []): void
    {
        Gate::authorize('stock.adjust');

        $disposition = $attributes['disposition'] ?? AssetRetirementDisposition::Retired;
        $disposition = $disposition instanceof AssetRetirementDisposition
            ? $disposition
            : AssetRetirementDisposition::from((string) $disposition);
        $attributes['disposition'] = $disposition;
        $eventType = match ($disposition) {
            AssetRetirementDisposition::Sold => AssetLifecycleEventType::Sold,
            AssetRetirementDisposition::Disposed => AssetLifecycleEventType::Disposed,
            AssetRetirementDisposition::Retired => AssetLifecycleEventType::Retired,
        };

        app(AssetLifecycleTransitioner::class)->transition($asset, $eventType, AssetLifecycleStatus::Retired, $attributes);
    }
}
