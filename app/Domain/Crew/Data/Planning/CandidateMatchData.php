<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Planning;

use Spatie\LaravelData\Data;

final class CandidateMatchData extends Data
{
    /**
     * @param  list<array{code:string,message:string}>  $blockers
     * @param  list<array{code:string,message:string}>  $warnings
     */
    public function __construct(public int $crew_member_id, public string $name, public int $score, public array $blockers = [], public array $warnings = []) {}
}
