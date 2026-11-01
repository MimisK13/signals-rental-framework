<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Planning;

use Spatie\LaravelData\Data;

final class CrewCoverageData extends Data
{
    /** @param list<CrewCoverageLineData> $lines */
    public function __construct(public int $request_id, public array $lines) {}
}
