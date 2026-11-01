<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Planning;

use Spatie\LaravelData\Data;

final class MatchCrewCandidatesData extends Data
{
    public function __construct(public int $limit = 50, public bool $include_blocked = false, public int $offset = 0) {}
}
