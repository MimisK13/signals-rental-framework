<?php

declare(strict_types=1);

namespace App\Domain\VirtualStock\Actions;

use App\Concerns\CommitsVerbsEvents;
use App\Domain\VirtualStock\Models\VirtualStockIntake;
use App\Domain\VirtualStock\Services\EnsuresVirtualStockIntakeIsDraft;
use App\Domain\VirtualStock\Services\VirtualStockIntakeEventRecorder;
use App\Domain\VirtualStock\Verbs\Events\VirtualStockIntakeDeleted;
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

        app(VirtualStockIntakeEventRecorder::class)->deleted($intake);
    }
}
