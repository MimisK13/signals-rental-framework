<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Rates;

use App\Domain\Crew\Enums\CrewRateType;
use Spatie\LaravelData\Data;

final class ResolveCrewRateData extends Data
{
    public function __construct(
        public int $crew_member_id,
        public int $crew_role_id,
        public int $store_id,
        public string $effective_date,
        public string $currency_code = 'GBP',
        public CrewRateType $rate_type = CrewRateType::Hourly,
    ) {}

}
