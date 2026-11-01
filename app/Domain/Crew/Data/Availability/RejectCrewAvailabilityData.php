<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Availability;

use Spatie\LaravelData\Data;

final class RejectCrewAvailabilityData extends Data
{
    public function __construct(public string $reason) {}
}
