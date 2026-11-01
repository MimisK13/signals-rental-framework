<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Availability;

use Spatie\LaravelData\Data;

final class RequestCrewAvailabilityData extends Data
{
    public function __construct(public string $starts_at, public string $ends_at, public string $type = 'unavailable', public string $timezone = 'UTC', public ?string $reason = null, public bool $all_day = false) {}
}
