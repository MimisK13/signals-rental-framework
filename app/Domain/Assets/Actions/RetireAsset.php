<?php

declare(strict_types=1);

namespace App\Domain\Assets\Actions;

use App\Domain\Assets\Enums\AssetLifecycleEventType;
use App\Domain\Assets\Enums\AssetLifecycleStatus;
use App\Domain\Assets\Enums\AssetRetirementDisposition;
use App\Domain\Assets\Models\Asset;
use App\Domain\Assets\Services\AssetLifecycleTransitioner;
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
