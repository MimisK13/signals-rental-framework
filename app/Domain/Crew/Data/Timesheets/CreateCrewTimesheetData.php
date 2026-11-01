<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Timesheets;

use Spatie\LaravelData\Data;

final class CreateCrewTimesheetData extends Data
{
    public function __construct(
        public string $period_start,
        public string $period_end,
        public bool $seed_from_shifts = true,
    ) {}

}
