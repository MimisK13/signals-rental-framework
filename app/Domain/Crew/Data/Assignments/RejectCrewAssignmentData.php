<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Assignments;

use Spatie\LaravelData\Data;

final class RejectCrewAssignmentData extends Data
{
    public function __construct(public string $reason) {}
}
