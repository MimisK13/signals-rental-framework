<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Assignments;

use Spatie\LaravelData\Data;

final class SubstituteCrewAssignmentData extends Data
{
    public function __construct(public int $crew_member_id, public string $reason, public bool $copy_shifts = true) {}
}
