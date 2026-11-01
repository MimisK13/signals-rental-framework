<?php

declare(strict_types=1);

namespace App\Domain\Crew\Queries;

use App\Domain\Crew\Data\Planning\CrewCoverageData;
use App\Domain\Crew\Data\Planning\CrewCoverageLineData;
use App\Domain\Crew\Models\CrewRequest;
use Illuminate\Support\Facades\Gate;

final class CrewCoverageQuery
{
    public function forRequest(CrewRequest $request): CrewCoverageData
    {
        Gate::authorize('view', $request);

        $lines = $request->lines()->with('role')->withCount(['assignments as confirmed_count' => function ($query): void {
            $query->where('status', 'confirmed');
        }])->get();

        return new CrewCoverageData($request->id, $lines->map(function ($line): CrewCoverageLineData {
            return new CrewCoverageLineData($line->id, $line->role->name, $line->quantity, (int) $line->getAttribute('confirmed_count'), max(0, $line->quantity - (int) $line->getAttribute('confirmed_count')));
        })->all());
    }
}
