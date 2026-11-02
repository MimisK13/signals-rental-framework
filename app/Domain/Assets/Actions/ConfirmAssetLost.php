<?php

declare(strict_types=1);

namespace App\Domain\Assets\Actions;

use App\Domain\Assets\Enums\AssetLifecycleEventType;
use App\Domain\Assets\Enums\AssetLifecycleStatus;
use App\Domain\Assets\Models\Asset;
use App\Domain\Assets\Services\AssetLifecycleTransitioner;
use Illuminate\Support\Facades\Gate;

class ConfirmAssetLost
{
    /** @param array<string, mixed> $attributes */
    public function __invoke(Asset $asset, array $attributes = []): void
    {
        Gate::authorize('stock.adjust');

        app(AssetLifecycleTransitioner::class)->transition($asset, AssetLifecycleEventType::MarkedLost, AssetLifecycleStatus::Lost, $attributes);
    }
}
