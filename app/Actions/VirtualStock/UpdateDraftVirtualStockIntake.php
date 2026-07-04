<?php

namespace App\Actions\VirtualStock;

use App\Concerns\CommitsVerbsEvents;
use App\Data\VirtualStock\UpdateDraftVirtualStockIntakeData;
use App\Data\VirtualStock\VirtualStockIntakeData;
use App\Models\VirtualStockIntake;
use App\Services\VirtualStock\EnsuresVirtualStockIntakeIsDraft;
use App\Verbs\Events\VirtualStock\VirtualStockIntakeUpdated;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

class UpdateDraftVirtualStockIntake
{
    use CommitsVerbsEvents;

    public function __invoke(VirtualStockIntake $intake, UpdateDraftVirtualStockIntakeData $data): VirtualStockIntakeData
    {
        Gate::authorize('update', $intake);
        app(EnsuresVirtualStockIntakeIsDraft::class)->assert($intake);

        $currency = $data->currency_code !== null && $data->currency_code !== ''
            ? strtoupper($data->currency_code)
            : $intake->currency_code;

        $updatedAt = CarbonImmutable::now('UTC');

        $this->commitVerbs(function () use ($intake, $data, $currency, $updatedAt): void {
            VirtualStockIntakeUpdated::fire(
                state_id: (int) $intake->state_id,
                purchase_order_id: $data->purchase_order_id,
                purchase_order_item_id: $data->purchase_order_item_id,
                product_id: $data->product_id,
                store_id: $data->store_id,
                quantity: $data->quantity,
                available_from: $data->available_from,
                available_until: $data->available_until,
                unit_cost: $data->unit_cost,
                currency_code: $currency,
                notes: $data->notes,
                updated_at: $updatedAt->toIso8601String(),
            );
        });

        return VirtualStockIntakeData::fromModel($intake->refresh());
    }
}
