<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Actions;

use App\Concerns\CommitsVerbsEvents;
use App\Domain\Assets\Enums\AssetCondition;
use App\Domain\Assets\Repairs\Actions\CreateRepair;
use App\Domain\Assets\Repairs\Actions\SetRepairUsability;
use App\Domain\Assets\Repairs\Models\Repair;
use App\Domain\Projects\Opportunities\Data\CheckAssetData;
use App\Domain\Projects\Opportunities\Models\OpportunityItemAsset;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Atomically finalises a returned asset inspection and optionally hands a
 * damaged serialized Asset to the existing Repair domain.
 */
class FinaliseAssetReturnInspection
{
    use CommitsVerbsEvents;

    public function __invoke(
        OpportunityItemAsset $assignment,
        CheckAssetData $data,
        bool $sendToRepair = false,
        bool $unusable = false,
    ): ?Repair {
        Gate::authorize('update', $assignment->item->opportunity);

        if ($sendToRepair && $data->condition !== AssetCondition::Damaged->value) {
            throw ValidationException::withMessages([
                'repair' => __('Only a damaged return can be sent to repair.'),
            ]);
        }

        if ($unusable && ! $sendToRepair) {
            throw ValidationException::withMessages([
                'repair' => __('An asset can only be marked unusable when it is sent to repair.'),
            ]);
        }

        return $this->commitVerbs(function () use ($assignment, $data, $sendToRepair, $unusable): ?Repair {
            $locked = OpportunityItemAsset::query()
                ->with(['item', 'stockLevel.asset'])
                ->whereKey($assignment->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                throw (new ModelNotFoundException)->setModel(OpportunityItemAsset::class, [$assignment->id]);
            }

            $repair = null;
            (new CheckAsset)($locked, $data);

            if (! $sendToRepair) {
                return null;
            }

            $stockLevel = $locked->stockLevel;
            $assetId = $stockLevel?->asset_id;

            if ($assetId === null) {
                throw ValidationException::withMessages([
                    'asset' => __('The returned serialized asset is not linked to a physical Asset record.'),
                ]);
            }

            $storeId = $locked->item->return_store_id ?? $stockLevel->store_id;

            $repair = (new CreateRepair)([
                'asset_id' => $assetId,
                'store_id' => $storeId,
                'description' => __('Damage found during rental return inspection.'),
                'notes' => $data->damage_notes,
                'is_usable' => true,
            ]);

            if ($unusable) {
                $repair = (new SetRepairUsability)($repair, false);
            }

            return $repair;
        });
    }
}
