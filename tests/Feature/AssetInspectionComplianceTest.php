<?php

use App\Actions\Inspections\CancelAssetInspection;
use App\Actions\Inspections\CompleteAssetInspection;
use App\Actions\Inspections\InitializeAssetInspection;
use App\Actions\Inspections\StartAssetInspection;
use App\Enums\InspectionComplianceState;
use App\Enums\InspectionResult;
use App\Enums\InspectionStatus;
use App\Enums\StockMethod;
use App\Models\Asset;
use App\Models\AssetInspection;
use App\Models\Attachment;
use App\Models\Demand;
use App\Models\InspectionPolicy;
use App\Models\Product;
use App\Models\User;
use App\Services\Assets\AssetInspectionComplianceReader;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->actingAs(User::factory()->owner()->create());
});

/**
 * @return array{0: Product, 1: Asset, 2: InspectionPolicy}
 */
function inspectionFixture(): array
{
    $product = Product::factory()->serialised()->create();
    $asset = Asset::factory()->create(['product_id' => $product->id]);
    $policy = InspectionPolicy::factory()->create(['product_id' => $product->id]);

    return [$product, $asset, $policy];
}

it('requires serialized asset and product matching policy', function (): void {
    [$product, $asset, $policy] = inspectionFixture();

    (new InitializeAssetInspection)($asset, $policy, '2027-03-01');

    $otherProduct = Product::factory()->serialised()->create();
    $otherAsset = Asset::factory()->create(['product_id' => $otherProduct->id]);

    expect(fn () => (new InitializeAssetInspection)($otherAsset, $policy, '2027-03-01'))
        ->toThrow(ValidationException::class);

    expect($product->stock_method)->not->toBe(StockMethod::Bulk);
});

it('derives fixed due dates without shifting for early or late completion', function (): void {
    [, $asset, $policy] = inspectionFixture();
    $inspection = (new InitializeAssetInspection)($asset, $policy, '2027-03-01');

    (new StartAssetInspection)($inspection);
    (new CompleteAssetInspection)($inspection->refresh(), InspectionResult::Passed);

    $compliance = app(AssetInspectionComplianceReader::class)->forAssetPolicy(
        $asset->refresh(),
        $policy,
        Carbon::parse('2027-03-16')->toImmutable(),
    );

    expect($compliance->state)->toBe(InspectionComplianceState::Valid)
        ->and($compliance->nextDueAt?->toDateString())->toBe('2028-03-01');
});

it('reports unknown when a policy has not been initialized', function (): void {
    [, $asset, $policy] = inspectionFixture();

    $compliance = app(AssetInspectionComplianceReader::class)->forAssetPolicy(
        $asset,
        $policy,
        Carbon::parse('2026-10-07')->toImmutable(),
    );

    expect($compliance->state)->toBe(InspectionComplianceState::Unknown)
        ->and($compliance->nextDueAt)->toBeNull();
});

it('derives overdue and due soon without creating availability demand', function (): void {
    [, $asset, $policy] = inspectionFixture();
    (new InitializeAssetInspection)($asset, $policy, '2026-09-01');

    $reader = app(AssetInspectionComplianceReader::class);
    $overdue = $reader->forAssetPolicy($asset, $policy, Carbon::parse('2026-10-07')->toImmutable());
    $soon = $reader->forAssetPolicy($asset, $policy, Carbon::parse('2026-08-10')->toImmutable(), 30);

    expect($overdue->state)->toBe(InspectionComplianceState::Overdue)
        ->and($soon->state)->toBe(InspectionComplianceState::DueSoon)
        ->and(Demand::query()->where('source_type', 'inspection')->exists())->toBeFalse();
});

it('keeps failed history after a passing reinspection and uses the latest result', function (): void {
    [, $asset, $policy] = inspectionFixture();
    $failed = AssetInspection::factory()->create([
        'asset_id' => $asset->id,
        'inspection_policy_id' => $policy->id,
        'due_at' => '2026-10-01',
        'status' => InspectionStatus::Completed,
        'result' => InspectionResult::Failed,
        'completed_at' => '2026-10-01 10:00:00',
    ]);
    $passed = AssetInspection::factory()->create([
        'asset_id' => $asset->id,
        'inspection_policy_id' => $policy->id,
        'due_at' => '2026-10-03',
        'status' => InspectionStatus::Completed,
        'result' => InspectionResult::Passed,
        'completed_at' => '2026-10-03 10:00:00',
    ]);

    $compliance = app(AssetInspectionComplianceReader::class)->forAssetPolicy(
        $asset->refresh(),
        $policy,
        Carbon::parse('2026-10-04')->toImmutable(),
    );

    expect($failed->refresh()->result)->toBe(InspectionResult::Failed)
        ->and($passed->refresh()->result)->toBe(InspectionResult::Passed)
        ->and($compliance->state)->toBe(InspectionComplianceState::Valid)
        ->and($compliance->nextDueAt?->toDateString())->toBe('2027-10-03');
});

it('aggregates multiple policies by severity', function (): void {
    [$product, $asset, $policy] = inspectionFixture();
    $second = InspectionPolicy::factory()->create([
        'product_id' => $product->id,
        'name' => 'Electrical safety',
        'interval_months' => 24,
    ]);
    (new InitializeAssetInspection)($asset, $policy, '2027-03-01');
    (new InitializeAssetInspection)($asset, $second, '2026-09-01');

    $counts = app(AssetInspectionComplianceReader::class)->summarizeProduct(
        $product,
        Carbon::parse('2026-10-07')->toImmutable(),
    );

    expect($counts[InspectionComplianceState::Overdue->value])->toBe(1);
});

it('does not allow completed inspections to be rewritten', function (): void {
    [, $asset, $policy] = inspectionFixture();
    $inspection = AssetInspection::factory()->create([
        'asset_id' => $asset->id,
        'inspection_policy_id' => $policy->id,
        'status' => InspectionStatus::Completed,
        'result' => InspectionResult::Passed,
        'completed_at' => now(),
    ]);

    expect(fn () => $inspection->update(['result' => InspectionResult::Failed]))
        ->toThrow(ValidationException::class);
});

it('supports explicit cancellation without contributing compliance', function (): void {
    [, $asset, $policy] = inspectionFixture();
    $inspection = (new InitializeAssetInspection)($asset, $policy, '2026-09-01');
    (new CancelAssetInspection)($inspection);

    $compliance = app(AssetInspectionComplianceReader::class)->forAssetPolicy(
        $asset->refresh(),
        $policy,
        Carbon::parse('2026-10-07')->toImmutable(),
    );

    expect($inspection->refresh()->status)->toBe(InspectionStatus::Cancelled)
        ->and($compliance->state)->toBe(InspectionComplianceState::Unknown);
});

it('supports the existing attachment relationship for inspection records', function (): void {
    [, $asset, $policy] = inspectionFixture();
    $inspection = (new InitializeAssetInspection)($asset, $policy, '2027-03-01');
    $attachment = Attachment::factory()->create([
        'attachable_type' => AssetInspection::class,
        'attachable_id' => $inspection->id,
    ]);

    expect($inspection->refresh()->attachments->first()->is($attachment))->toBeTrue();
});
