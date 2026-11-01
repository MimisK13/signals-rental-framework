<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Timesheets;

use Spatie\LaravelData\Data;

final class ApproveCrewTimesheetData extends Data
{
    public function __construct(
        public ?int $version = null,
    ) {}

}
