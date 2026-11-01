<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Rates;

use App\Domain\Crew\Enums\CrewRateType;
use Spatie\LaravelData\Data;

final class ResolvedCrewRateData extends Data
{
    /** @param array<int|string, array<string, mixed>> $allowances_snapshot */
    public function __construct(
        public ?int $rate_card_id,
        public CrewRateType $rate_type,
        public int $rate_amount_minor,
        public string $currency_code,
        public string $overtime_multiplier,
        public string $min_call_hours,
        public string $daily_call_hours,
        public array $allowances_snapshot = [],
    ) {}

}
