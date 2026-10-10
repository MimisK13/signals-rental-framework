<?php

namespace App\Services\Assets;

use App\Enums\AssetLifecycleEventType;
use App\Enums\AssetLifecycleStatus;
use App\Enums\AssetOwnershipType;
use App\Enums\AssetRetirementDisposition;
use App\Enums\InspectionStatus;
use App\Enums\RepairStatus;
use App\Enums\StockCategory;
use App\Enums\TransactionType;
use App\Events\AuditableEvent;
use App\Jobs\RecalculateAvailabilityJob;
use App\Models\Asset;
use App\Models\AssetInspection;
use App\Models\AssetLifecycleEvent;
use App\Models\StockLevel;
use App\Models\StockTransaction;
use App\Services\Availability\ProductStoreCapacityLock;
use App\Services\Availability\RepairDemandResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AssetLifecycleTransitioner
{
    /** @param array<string, mixed> $attributes */
    public function transition(
        Asset $asset,
        AssetLifecycleEventType $eventType,
        AssetLifecycleStatus $newStatus,
        array $attributes = [],
    ): AssetLifecycleEvent {
        return DB::transaction(function () use ($asset, $eventType, $newStatus, $attributes): AssetLifecycleEvent {
            $asset = Asset::query()->lockForUpdate()->findOrFail($asset->id);
            $currentStockLevel = $asset->currentStockLevel()->first();
            if ($currentStockLevel !== null) {
                Gate::authorize('access', $currentStockLevel);
            }
            $scopes = [];
            if ($currentStockLevel !== null) {
                $scopes[] = ['product_id' => (int) $asset->product_id, 'store_id' => (int) $currentStockLevel->store_id];
            }
            if (isset($attributes['store_id'])) {
                $scopes[] = ['product_id' => (int) $asset->product_id, 'store_id' => (int) $attributes['store_id']];
            }
            if ($scopes !== []) {
                app(ProductStoreCapacityLock::class)->acquireMany($scopes);
            }
            $previousStatus = $asset->lifecycle_status ?? AssetLifecycleStatus::Active;

            $this->assertTransition($asset, $eventType, $previousStatus, $newStatus, $attributes);

            if ($newStatus === AssetLifecycleStatus::Retired) {
                $this->assertNoActiveRepair($asset);
                $this->cancelPendingInspections($asset);
            }

            if ($eventType === AssetLifecycleEventType::Found || $eventType === AssetLifecycleEventType::Reactivated) {
                $this->restoreOperationalStock($asset, $attributes);
            }

            $asset->forceFill(['lifecycle_status' => $newStatus])->saveQuietly();
            app(RepairDemandResolver::class)->syncForAsset($asset->id);

            $stockLevel = $asset->currentStockLevel()->first();
            $actor = Auth::user();
            $event = AssetLifecycleEvent::create([
                'asset_id' => $asset->id,
                'event_type' => $eventType,
                'occurred_at' => $attributes['occurred_at'] ?? Carbon::now(),
                'actor_user_id' => $actor?->id,
                'actor_member_id' => $actor?->member_id,
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
                'store_id' => $attributes['store_id'] ?? $stockLevel?->store_id,
                'location' => $attributes['location'] ?? $stockLevel?->location,
                'disposition' => $attributes['disposition'] ?? null,
                'proceeds_amount' => $attributes['proceeds_amount'] ?? null,
                'proceeds_currency_code' => $attributes['proceeds_currency_code'] ?? null,
                'buyer_member_id' => $attributes['buyer_member_id'] ?? null,
                'reference' => $attributes['reference'] ?? null,
                'reason' => $attributes['reason'] ?? null,
                'notes' => $attributes['notes'] ?? null,
            ]);

            event(new AuditableEvent(
                $event,
                'asset.lifecycle.'.$eventType->value,
                ['lifecycle_status' => $previousStatus->value],
                ['lifecycle_status' => $newStatus->value],
            ));

            if ($stockLevel !== null && $asset->product?->track_availability) {
                DB::afterCommit(static function () use ($asset, $stockLevel): void {
                    RecalculateAvailabilityJob::dispatch((int) $asset->product_id, (int) $stockLevel->store_id);
                });
            }

            return $event;
        });
    }

    /** @param array<string, mixed> $attributes */
    private function assertTransition(
        Asset $asset,
        AssetLifecycleEventType $eventType,
        AssetLifecycleStatus $current,
        AssetLifecycleStatus $newStatus,
        array $attributes,
    ): void {
        $valid = match ($eventType) {
            AssetLifecycleEventType::MarkedMissing => $current === AssetLifecycleStatus::Active && $newStatus === AssetLifecycleStatus::Missing,
            AssetLifecycleEventType::MarkedLost => $current === AssetLifecycleStatus::Missing && $newStatus === AssetLifecycleStatus::Lost,
            AssetLifecycleEventType::Found => in_array($current, [AssetLifecycleStatus::Missing, AssetLifecycleStatus::Lost], true)
                && $newStatus === AssetLifecycleStatus::Active,
            AssetLifecycleEventType::Retired,
            AssetLifecycleEventType::Sold,
            AssetLifecycleEventType::Disposed => in_array($current, [AssetLifecycleStatus::Active, AssetLifecycleStatus::Lost], true)
                && $newStatus === AssetLifecycleStatus::Retired,
            AssetLifecycleEventType::Reactivated => $current === AssetLifecycleStatus::Retired
                && $newStatus === AssetLifecycleStatus::Active
                && ! $asset->lifecycleEvents()->whereIn('disposition', [AssetRetirementDisposition::Sold, AssetRetirementDisposition::Disposed])->exists(),
        };

        if (! $valid) {
            throw ValidationException::withMessages(['lifecycle_status' => 'This Asset lifecycle transition is not allowed.']);
        }

        if (in_array($eventType, [AssetLifecycleEventType::Sold, AssetLifecycleEventType::Disposed], true)
            && AssetOwnershipType::tryFrom((string) $asset->getRawOriginal('ownership_type')) !== AssetOwnershipType::CompanyOwned) {
            throw ValidationException::withMessages(['disposition' => 'Only company-owned Assets can be sold or disposed.']);
        }

        if (in_array($eventType, [AssetLifecycleEventType::Found, AssetLifecycleEventType::Reactivated], true)
            && empty($attributes['store_id'])) {
            throw ValidationException::withMessages(['store_id' => 'A Store is required to restore an Asset operationally.']);
        }

        if (($attributes['proceeds_amount'] ?? null) !== null && (int) $attributes['proceeds_amount'] < 0) {
            throw ValidationException::withMessages(['proceeds_amount' => 'Disposition proceeds cannot be negative.']);
        }

        if (($attributes['proceeds_amount'] ?? null) !== null
            && ! preg_match('/^[A-Z]{3}$/', (string) ($attributes['proceeds_currency_code'] ?? ''))) {
            throw ValidationException::withMessages(['proceeds_currency_code' => 'A three-letter proceeds currency is required.']);
        }
    }

    private function assertNoActiveRepair(Asset $asset): void
    {
        if ($asset->repairs()->where('status', '!=', RepairStatus::Completed->value)->exists()) {
            throw ValidationException::withMessages(['lifecycle_status' => 'Complete or resolve active Repairs before retiring this Asset.']);
        }
    }

    private function cancelPendingInspections(Asset $asset): void
    {
        $asset->inspections()
            ->whereIn('status', [InspectionStatus::Pending->value, InspectionStatus::InProgress->value])
            ->get()
            ->each(function (AssetInspection $inspection): void {
                $inspection->forceFill(['status' => InspectionStatus::Cancelled])->saveQuietly();
            });
    }

    /** @param array<string, mixed> $attributes */
    private function restoreOperationalStock(Asset $asset, array $attributes): void
    {
        $stockLevel = $asset->currentStockLevel()->first();

        if ($stockLevel !== null) {
            $stockLevel->forceFill(array_filter([
                'store_id' => $attributes['store_id'] ?? null,
                'location' => array_key_exists('location', $attributes) ? $attributes['location'] : null,
            ], static fn (mixed $value): bool => $value !== null))->save();

            return;
        }

        $storeId = $attributes['store_id'] ?? null;
        if ($storeId === null) {
            throw ValidationException::withMessages(['store_id' => 'A Store is required to restore an Asset without a current StockLevel.']);
        }

        $stockLevel = StockLevel::create([
            'product_id' => $asset->product_id,
            'store_id' => $storeId,
            'item_name' => $asset->product?->name,
            'asset_number' => $attributes['asset_number'] ?? null,
            'serial_number' => $attributes['serial_number'] ?? null,
            'barcode' => $attributes['barcode'] ?? null,
            'location' => $attributes['location'] ?? null,
            'stock_category' => StockCategory::SerialisedStock,
            'quantity_held' => 1,
            'quantity_allocated' => 0,
            'quantity_unavailable' => 0,
            'quantity_on_order' => 0,
            'asset_id' => $asset->id,
        ]);

        StockTransaction::create([
            'stock_level_id' => $stockLevel->id,
            'store_id' => $stockLevel->store_id,
            'transaction_type' => TransactionType::Find,
            'transaction_at' => now(),
            'quantity' => 1,
            'quantity_before' => '0.00',
            'quantity_after' => '1.00',
            'actor_user_id' => Auth::id(),
            'description' => 'Asset found and restored to stock.',
            'manual' => true,
        ]);
    }
}
