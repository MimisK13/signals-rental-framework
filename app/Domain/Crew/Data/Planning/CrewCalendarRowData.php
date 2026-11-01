<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Planning;

use App\Domain\Crew\Data\Shifts\CrewShiftData;
use Spatie\LaravelData\Data;

final class CrewCalendarRowData extends Data
{
    /** @param list<CrewShiftData> $shifts */
    public function __construct(public int $crew_member_id, public string $name, public array $shifts) {}
}
