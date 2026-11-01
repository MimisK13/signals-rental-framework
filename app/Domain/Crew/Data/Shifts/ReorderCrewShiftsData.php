<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Shifts;

use Spatie\LaravelData\Data;

final class ReorderCrewShiftsData extends Data
{
    /** @param list<int> $shift_ids */
    public function __construct(public array $shift_ids) {}
}
