<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Requests;

use Spatie\LaravelData\Data;

final class CreateCrewRequestData extends Data
{
    public function __construct(public string $title, public int $project_id, public int $store_id, public string $starts_at, public string $ends_at, public ?int $opportunity_id = null, public string $timezone = 'UTC', public ?string $notes = null) {}
}
