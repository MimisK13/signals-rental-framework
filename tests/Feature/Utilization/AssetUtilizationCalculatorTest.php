<?php

use App\Enums\AssetLifecycleEventType;
use App\Enums\AssetLifecycleStatus;
use App\Enums\AssetOwnershipType;
use App\Models\Asset;
use App\Models\AssetLifecycleEvent;
use App\Models\AssetUsage;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\Repair;
use App\Services\Assets\AssetUtilizationCalculator;
use Carbon\CarbonImmutable;

function utilizationAsset(string $acquiredAt = '2026-01-01 00:00:00'): Asset
{
    $product = Product::factory()->serialised()->create();
    $asset = Asset::factory()->create([
        'product_id' => $product->id,
        'ownership_type' => AssetOwnershipType::CompanyOwned,
        'acquired_at' => $acquiredAt,
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

    return $asset->refresh();
}

function utilizationUsage(Asset $asset, string $from, ?string $until): AssetUsage
{
    return AssetUsage::factory()->create([
        'asset_id' => $asset->id,
        'opportunity_item_id' => OpportunityItem::factory()->create([
            'itemable_id' => $asset->product_id,
            'itemable_type' => Product::class,
        ])->id,
        'dispatched_at' => $from,
        'returned_at' => $until,
    ]);
}

function utilizationCalculator(): AssetUtilizationCalculator
{
    return app(AssetUtilizationCalculator::class);
}

/**
 * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: CarbonImmutable}
 */
function utilizationWindow(): array
{
    return [
        CarbonImmutable::parse('2026-01-01 00:00:00', 'UTC'),
        CarbonImmutable::parse('2026-01-05 04:00:00', 'UTC'),
        CarbonImmutable::parse('2026-01-05 04:00:00', 'UTC'),
    ];
}

it('calculates duration based actual utilization and idle time', function (): void {
    $asset = utilizationAsset();
    utilizationUsage($asset, '2026-01-01 00:00:00', '2026-01-03 12:00:00');
    [$from, $until, $asOf] = utilizationWindow();

    $result = utilizationCalculator()->calculate($asset, $from, $until, $asOf);

    expect($result->operationalLifetime)->toBe(360000)
        ->and($result->plannableDuration)->toBe(360000)
        ->and($result->actualRentalDuration)->toBe(216000)
        ->and($result->rentalWithinPlannableDuration)->toBe(216000)
        ->and($result->idlePlannableDuration)->toBe(144000)
        ->and($result->actualRentalUtilizationRatio)->toBe('0.6000000000');
});

it('unions overlapping repair and lifecycle downtime once', function (): void {
    $asset = utilizationAsset();
    AssetLifecycleEvent::factory()->create([
        'asset_id' => $asset->id,
        'event_type' => AssetLifecycleEventType::MarkedMissing,
        'occurred_at' => '2026-01-02 00:00:00',
        'previous_status' => AssetLifecycleStatus::Active,
        'new_status' => AssetLifecycleStatus::Missing,
    ]);
    AssetLifecycleEvent::factory()->create([
        'asset_id' => $asset->id,
        'event_type' => AssetLifecycleEventType::Found,
        'occurred_at' => '2026-01-02 20:00:00',
        'previous_status' => AssetLifecycleStatus::Missing,
        'new_status' => AssetLifecycleStatus::Active,
    ]);
    Repair::factory()->forAsset($asset)->unusable()->create([
        'reported_at' => '2026-01-02 10:00:00',
        'started_at' => '2026-01-02 10:00:00',
        'completed_at' => '2026-01-03 06:00:00',
    ]);
    [$from, $until, $asOf] = utilizationWindow();

    $result = utilizationCalculator()->calculate($asset, $from, $until, $asOf);

    expect($result->lifecycleBlockedDuration)->toBe(72000)
        ->and($result->maintenanceBlockedDuration)->toBe(36000)
        ->and($result->plannableDuration)->toBe(252000);
});

it('keeps factual rental duration when rental overlaps missing time', function (): void {
    $asset = utilizationAsset();
    utilizationUsage($asset, '2026-01-01 00:00:00', '2026-01-05 00:00:00');
    AssetLifecycleEvent::factory()->create([
        'asset_id' => $asset->id,
        'event_type' => AssetLifecycleEventType::MarkedMissing,
        'occurred_at' => '2026-01-03 00:00:00',
        'previous_status' => AssetLifecycleStatus::Active,
        'new_status' => AssetLifecycleStatus::Missing,
    ]);
    AssetLifecycleEvent::factory()->create([
        'asset_id' => $asset->id,
        'event_type' => AssetLifecycleEventType::Found,
        'occurred_at' => '2026-01-05 00:00:00',
        'previous_status' => AssetLifecycleStatus::Missing,
        'new_status' => AssetLifecycleStatus::Active,
    ]);
    [$from, $until, $asOf] = utilizationWindow();

    $result = utilizationCalculator()->calculate($asset, $from, $until, $asOf);

    expect($result->actualRentalDuration)->toBe(345600)
        ->and($result->rentalWithinPlannableDuration)->toBe(172800);
});

it('reports unknown acquisition without fabricating a denominator', function (): void {
    $asset = utilizationAsset();
    $asset->forceFill(['acquired_at' => null])->saveQuietly();
    utilizationUsage($asset, '2026-01-01 00:00:00', '2026-01-02 00:00:00');
    [$from, $until, $asOf] = utilizationWindow();

    $result = utilizationCalculator()->calculate($asset, $from, $until, $asOf);

    expect($result->actualRentalDuration)->toBe(86400)
        ->and($result->actualRentalUtilizationRatio)->toBeNull()
        ->and($result->unavailableReasons)->toContain('acquisition_unknown');
});

it('bounds the denominator at retirement and uses asOf for open intervals', function (): void {
    $asset = utilizationAsset();
    utilizationUsage($asset, '2026-01-01 20:00:00', null);
    Repair::factory()->forAsset($asset)->unusable()->create([
        'reported_at' => '2026-01-03 04:00:00',
        'started_at' => '2026-01-03 04:00:00',
    ]);
    AssetLifecycleEvent::factory()->create([
        'asset_id' => $asset->id,
        'event_type' => AssetLifecycleEventType::Retired,
        'occurred_at' => '2026-01-03 12:00:00',
        'previous_status' => AssetLifecycleStatus::Active,
        'new_status' => AssetLifecycleStatus::Retired,
    ]);
    $from = CarbonImmutable::parse('2026-01-01 00:00:00', 'UTC');
    $until = CarbonImmutable::parse('2026-01-05 00:00:00', 'UTC');
    $asOf = CarbonImmutable::parse('2026-01-04 00:00:00', 'UTC');

    $result = utilizationCalculator()->calculate($asset, $from, $until, $asOf);

    expect($result->operationalLifetime)->toBe(216000)
        ->and($result->actualRentalDuration)->toBe(187200)
        ->and($result->maintenanceDowntime)->toBe(72000);
});

it('returns unavailable when lifecycle blocks the complete operational interval', function (): void {
    $asset = utilizationAsset();
    AssetLifecycleEvent::factory()->create([
        'asset_id' => $asset->id,
        'event_type' => AssetLifecycleEventType::MarkedMissing,
        'occurred_at' => '2026-01-01 00:00:00',
        'previous_status' => AssetLifecycleStatus::Active,
        'new_status' => AssetLifecycleStatus::Missing,
    ]);
    [$from, $until, $asOf] = utilizationWindow();

    $result = utilizationCalculator()->calculate($asset, $from, $until, $asOf);

    expect($result->plannableDuration)->toBe(0)
        ->and($result->actualRentalUtilizationRatio)->toBeNull()
        ->and($result->unavailableReasons)->toContain('no_plannable_time');
});

it('rejects an invalid half-open window', function (): void {
    $asset = utilizationAsset();
    $time = CarbonImmutable::parse('2026-01-01 00:00:00', 'UTC');

    expect(fn (): mixed => utilizationCalculator()->calculate($asset, $time, $time, $time))
        ->toThrow(InvalidArgumentException::class);
});
