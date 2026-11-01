<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Planning;

use Spatie\LaravelData\Data;

final class CrewCalendarFilterData extends Data
{
    public function __construct(public string $starts_at, public string $ends_at, public ?int $crew_member_id = null, public ?int $project_id = null) {}
}
