<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Planning;

use Spatie\LaravelData\Data;

final class CrewUtilisationRowData extends Data
{
    public function __construct(public int $crew_member_id, public string $name, public int $capacity_minutes, public int $booked_minutes, public int $worked_minutes, public float $utilisation_percent) {}
}
