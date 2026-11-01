<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Requests;

use Spatie\LaravelData\Data;

final class UpdateCrewRequestData extends Data
{
    public function __construct(public string $title, public string $starts_at, public string $ends_at, public string $timezone = 'UTC', public ?string $notes = null) {}
}
