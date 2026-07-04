<?php

namespace App\Actions\VirtualStock;

use App\Concerns\CommitsVerbsEvents;
use App\Models\VirtualStockIntake;
use App\Services\VirtualStock\EnsuresVirtualStockIntakeIsDraft;
use App\Verbs\Events\VirtualStock\VirtualStockIntakeDeleted;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

class DeleteDraftVirtualStockIntake
{
    use CommitsVerbsEvents;

    public function __invoke(VirtualStockIntake $intake): void
    {
        Gate::authorize('delete', $intake);
        app(EnsuresVirtualStockIntakeIsDraft::class)->assert($intake);

        $deletedAt = CarbonImmutable::now('UTC');

        $this->commitVerbs(function () use ($intake, $deletedAt): void {
            VirtualStockIntakeDeleted::fire(
                state_id: (int) $intake->state_id,
                deleted_at: $deletedAt->toIso8601String(),
                deleted_by: auth()->id(),
            );
        });
    }
}
