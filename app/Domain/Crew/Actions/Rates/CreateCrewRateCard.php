<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Rates;

use App\Domain\Crew\Data\Rates\CreateCrewRateCardData;
use App\Domain\Crew\Data\Rates\CrewRateCardData;
use App\Domain\Crew\Models\CrewRateCard;
use App\Domain\Crew\Services\CrewAudit;
use App\Domain\Crew\Services\CrewRateCardWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class CreateCrewRateCard
{
    public function __construct(private CrewRateCardWriter $writer) {}

    public function __invoke(CreateCrewRateCardData $data): CrewRateCardData
    {
        Gate::authorize('create', CrewRateCard::class);

        return DB::transaction(function () use ($data): CrewRateCardData {

            $card = $this->writer->save($data->toArray());
            CrewAudit::record('crew.rate_card.created', $card);

            return CrewRateCardData::fromModel($card);
        });
    }
}
