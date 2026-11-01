<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Timesheets;

use Spatie\LaravelData\Data;

final class UpdateCrewTimeEntryData extends Data
{
    public function __construct(
        public string $started_at,
        public string $ended_at,
        public string $kind = 'work',
        public int $break_minutes = 0,
        public ?int $crew_shift_id = null,
        public ?string $notes = null,
        public ?int $version = null,
    ) {}

}
