<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Requests;

use App\Domain\Crew\Data\Requests\CrewRequestData;
use App\Domain\Crew\Enums\CrewRequestStatus;
use App\Domain\Crew\Models\CrewRequest;
use App\Domain\Crew\Services\CrewAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class RecalculateCrewRequestFill
{
    public function __invoke(CrewRequest $request): CrewRequestData
    {
        Gate::authorize('view', $request);

        return DB::transaction(function () use ($request): CrewRequestData {
            $request = CrewRequest::query()->lockForUpdate()->findOrFail($request->id);
            foreach ($request->lines()->lockForUpdate()->get() as $line) {
                $line->update(['filled_quantity' => $line->assignments()->where('status', 'confirmed')->count()]);
            }
            $lines = $request->lines()->get();
            if (! in_array($request->status, [CrewRequestStatus::Draft, CrewRequestStatus::Cancelled], true)) {
                $required = $lines->sum('quantity');
                $filled = $lines->sum('filled_quantity');
                $status = $required > 0 && $filled >= $required ? CrewRequestStatus::Filled : ($filled > 0 ? CrewRequestStatus::PartiallyFilled : CrewRequestStatus::Open);
                if ($request->status !== $status) {
                    $request->update(['status' => $status]);
                    app(CrewAudit::class)->record($status === CrewRequestStatus::Filled ? 'crew.request.filled' : 'crew.request.coverage_changed', $request);
                }
            }

            return CrewRequestData::fromModel($request);
        });
    }
}
