<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Rates;

use Spatie\LaravelData\Data;

final class UpdateCrewRateCardData extends Data
{
    public function __construct(
        public string $currency_code,
        public string $effective_from,
        public ?int $store_id = null,
        public ?int $crew_member_id = null,
        public ?int $crew_role_id = null,
        public ?string $engagement_type = null,
        public ?string $effective_to = null,
        public ?int $hourly_rate_minor = null,
        public ?int $daily_rate_minor = null,
        public ?int $fixed_rate_minor = null,
        public string $overtime_multiplier = '1.50',
        public ?string $weekend_multiplier = null,
        public ?string $night_multiplier = null,
        public ?string $holiday_multiplier = null,
        public ?string $min_call_hours = null,
        public ?string $daily_call_hours = null,
        public ?int $travel_rate_minor = null,
        public ?int $per_diem_minor = null,
        public ?string $notes = null,
        public bool $is_active = true,
    ) {}

}
