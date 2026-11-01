<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Planning;

use Spatie\LaravelData\Data;

final class CandidateMatchCollectionData extends Data
{
    /** @param list<CandidateMatchData> $candidates */
    public function __construct(public array $candidates, public bool $pool_truncated, public int $offset, public ?int $next_offset) {}
}
