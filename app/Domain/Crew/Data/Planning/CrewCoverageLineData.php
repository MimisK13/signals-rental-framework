<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Planning;

use Spatie\LaravelData\Data;

final class CrewCoverageLineData extends Data
{
    public function __construct(public int $line_id, public string $role, public int $quantity, public int $filled_quantity, public int $shortage) {}
}
