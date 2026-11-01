<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Requests;

use Spatie\LaravelData\Data;

final class UpdateCrewRequestLineData extends Data
{
    public function __construct(public int $quantity, public string $starts_at, public string $ends_at, public ?string $notes = null) {}
}
