<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Shifts;

use Spatie\LaravelData\Data;

final class RealignAssignmentShiftsData extends Data
{
    /** @param list<array{id:int,starts_at:string,ends_at:string,timezone:string}> $shifts */
    public function __construct(public array $shifts, public string $reason) {}
}
