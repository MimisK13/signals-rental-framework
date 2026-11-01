<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Rates;

use App\Domain\Crew\Data\Rates\CrewRateCardData;
use App\Domain\Crew\Data\Rates\UpdateCrewRateCardData;
use App\Domain\Crew\Models\CrewRateCard;
use App\Domain\Crew\Services\CrewAudit;
use App\Domain\Crew\Services\CrewRateCardWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class UpdateCrewRateCard
{
    public function __construct(private CrewRateCardWriter $writer) {}

    public function __invoke(CrewRateCard $card, UpdateCrewRateCardData $data): CrewRateCardData
    {
        Gate::authorize('update', $card);

        return DB::transaction(function () use ($card, $data): CrewRateCardData {
            $card = CrewRateCard::query()->lockForUpdate()->findOrFail($card->id);
            Gate::authorize('update', $card);
            $card = $this->writer->save($data->toArray(), $card);
            CrewAudit::record('crew.rate_card.updated', $card);

            return CrewRateCardData::fromModel($card);
        });
    }
}
