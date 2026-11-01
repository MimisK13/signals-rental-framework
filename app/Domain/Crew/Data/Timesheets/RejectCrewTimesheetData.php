<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Timesheets;

use Spatie\LaravelData\Data;

final class RejectCrewTimesheetData extends Data
{
    public function __construct(
        public string $reason,
        public ?int $version = null,
    ) {}

}
