<?php

namespace App\Actions\Containers;

use App\Data\Containers\ContainerItemData;
use App\Data\Containers\TransferContainerItemData;
use App\Enums\ContainerItemUnpackReason;
use App\Enums\ContainerStatus;
use App\Enums\ContainerUnsealReason;
use App\Events\Containers\BeforeContainerItemTransferred;
use App\Events\Containers\ContainerItemTransferred;
use App\Models\Container;
use App\Models\ContainerItem;
use App\Models\StockLevel;
use App\Services\Availability\ContainerDemandResolver;
use App\Services\Containers\ContainerEventRecorder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Atomically move a serialised item from one open container to another
 * (serialised-containers.md §"One container at a time — with transfer support").
 *
 * Unpacks from the source (reason transferred), packs into the target, and
 * auto-unseals a sealed source with reason {@see ContainerUnsealReason::TransferTriggered}.
 */
class TransferContainerItem
{
    public function __invoke(Container $target, TransferContainerItemData $data): ContainerItemData
    {
        Gate::authorize('transfer', $target);
        Gate::authorize('pack', $target);

        $source = Container::query()->findOrFail($data->source_container_id);

        if ($source->id === $target->id) {
            throw ValidationException::withMessages([
                'source_container_id' => __('Source and target containers must differ.'),
            ]);
        }

        Gate::authorize('transfer', $source);

        $membership = ContainerItem::query()
            ->where('container_id', $source->id)
            ->where('serialised_item_id', $data->serialised_item_id)
            ->whereNull('unpacked_at')
            ->first();

        if ($membership === null) {
            throw ValidationException::withMessages([
                'serialised_item_id' => __('The item is not packed in the source container.'),
            ]);
        }

        $stockLevel = StockLevel::query()->findOrFail($data->serialised_item_id);

        BeforeContainerItemTransferred::dispatch($source, $target, $stockLevel, $membership);

        return DB::transaction(function () use ($source, $target, $membership, $stockLevel, $data): ContainerItemData {
            if ($source->status === ContainerStatus::Sealed) {
                $source->update([
                    'status' => ContainerStatus::Open,
                    'unsealed_at' => Carbon::now('UTC'),
                    'unsealed_by_user_id' => Auth::id(),
                ]);
                $source->refresh();
                app(ContainerEventRecorder::class)->unsealed($source, ContainerUnsealReason::TransferTriggered);
            }

            if (! $source->status->acceptsPacking()) {
                throw ValidationException::withMessages([
                    'source_container_id' => __('Items can only be transferred from an open container.'),
                ]);
            }

            $membership->forceFill([
                'unpacked_at' => Carbon::now('UTC'),
                'unpacked_reason' => ContainerItemUnpackReason::Transferred,
                'transferred_to_container_id' => $target->id,
            ])->save();

            StockLevel::query()
                ->whereKey($membership->serialised_item_id)
                ->update([
                    'container_stock_level_id' => null,
                    'container_mode' => null,
                ]);

            $membership->setRelation('container', $source);
            app(ContainerDemandResolver::class)->releaseDemands($membership);
            app(ContainerEventRecorder::class)->itemUnpacked($source, $membership);

            $packer = app(PackContainerItem::class);

            $packed = $packer->executePack($target, $stockLevel, $data->position, $data->notes);

            app(ContainerEventRecorder::class)->itemTransferred(
                $source,
                $target,
                $packed,
            );

            ContainerItemTransferred::dispatch($source, $target, $packed);

            return ContainerItemData::fromModel($packed->fresh() ?? $packed);
        });
    }
}
