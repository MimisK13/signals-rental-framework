<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Shifts;

use Spatie\LaravelData\Data;

final class UpdateCrewShiftData extends Data
{
    public function __construct(public string $starts_at, public string $ends_at, public string $timezone = 'UTC', public string $type = 'show', public int $break_minutes = 0, public ?string $location_name = null, public ?string $notes = null) {}
}
