<?php

use App\Enums\AssetLifecycleEventType;
use App\Enums\AssetLifecycleStatus;
use App\Enums\AssetOwnershipType;
use App\Models\Asset;
use App\Models\AssetLifecycleEvent;
use App\Models\AssetUsage;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Services\Assets\ProductUtilizationCalculator;
use Carbon\CarbonImmutable;

it('weights product utilization by asset-time instead of averaging percentages', function (): void {
    $product = Product::factory()->serialised()->create();
    $from = CarbonImmutable::parse('2026-01-01 00:00:00', 'UTC');
    $until = CarbonImmutable::parse('2026-01-05 12:00:00', 'UTC');

    foreach ([90, 1] as $index => $rentalHours) {
        $asset = Asset::factory()->create([
            'product_id' => $product->id,
            'ownership_type' => AssetOwnershipType::CompanyOwned,
            'acquired_at' => '2025-01-01 00:00:00',
        ]);
        AssetLifecycleEvent::factory()->create([
            'asset_id' => $asset->id,
            'event_type' => AssetLifecycleEventType::MarkedMissing,
            'occurred_at' => '2025-12-31 00:00:00',
            'previous_status' => AssetLifecycleStatus::Active,
            'new_status' => AssetLifecycleStatus::Missing,
        ]);
        AssetLifecycleEvent::factory()->create([
            'asset_id' => $asset->id,
            'event_type' => AssetLifecycleEventType::Found,
            'occurred_at' => '2025-12-31 01:00:00',
            'previous_status' => AssetLifecycleStatus::Missing,
            'new_status' => AssetLifecycleStatus::Active,
        ]);
        if ($index === 0) {
            AssetLifecycleEvent::factory()->create([
                'asset_id' => $asset->id,
                'event_type' => AssetLifecycleEventType::Retired,
                'occurred_at' => '2026-01-05 04:00:00',
                'previous_status' => AssetLifecycleStatus::Active,
                'new_status' => AssetLifecycleStatus::Retired,
            ]);
        } else {
            AssetLifecycleEvent::factory()->create([
                'asset_id' => $asset->id,
                'event_type' => AssetLifecycleEventType::Retired,
                'occurred_at' => '2026-01-01 10:00:00',
                'previous_status' => AssetLifecycleStatus::Active,
                'new_status' => AssetLifecycleStatus::Retired,
            ]);
        }
        $item = OpportunityItem::factory()->create([
            'itemable_id' => $product->id,
            'itemable_type' => Product::class,
        ]);
        AssetUsage::factory()->create([
            'asset_id' => $asset->id,
            'opportunity_item_id' => $item->id,
            'dispatched_at' => '2026-01-01 00:00:00',
            'returned_at' => CarbonImmutable::parse('2026-01-01 00:00:00')->addHours($rentalHours),
        ]);
    }

    $result = app(ProductUtilizationCalculator::class)->calculate($product, $from, $until, $until);

    expect($result->actualRentalUtilizationRatio)->toBe('0.8272727273')
        ->and($result->assetCount)->toBe(2)
        ->and($result->assetsWithCompleteData)->toBe(2);
});
