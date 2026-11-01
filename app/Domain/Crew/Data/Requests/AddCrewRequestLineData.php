<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Requests;

use Spatie\LaravelData\Data;

final class AddCrewRequestLineData extends Data
{
    public function __construct(public int $crew_role_id, public int $quantity, public string $starts_at, public string $ends_at, public ?string $notes = null) {}
}
