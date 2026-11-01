<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Rates;

use App\Domain\Crew\Data\Rates\CrewRateCardData;
use App\Domain\Crew\Models\CrewRateCard;
use App\Domain\Crew\Services\CrewAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class DeactivateCrewRateCard
{
    public function __invoke(CrewRateCard $card): CrewRateCardData
    {
        Gate::authorize('update', $card);

        return DB::transaction(function () use ($card): CrewRateCardData {
            $card = CrewRateCard::query()->lockForUpdate()->findOrFail($card->id);
            Gate::authorize('update', $card);
            $card->update(['is_active' => false, 'effective_to' => max($card->effective_from->toDateString(), today()->toDateString())]);
            CrewAudit::record('crew.rate_card.deactivated', $card);

            return CrewRateCardData::fromModel($card);
        });
    }
}
