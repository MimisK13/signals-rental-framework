<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Shifts;

use Spatie\LaravelData\Data;

final class CancelCrewShiftData extends Data
{
    public function __construct(public string $reason) {}
}
