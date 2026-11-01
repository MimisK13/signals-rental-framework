<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Planning;

use Spatie\LaravelData\Data;

final class CrewConflictReportData extends Data
{
    /**
     * @param  list<array{code:string,message:string}>  $hard
     * @param  list<array{code:string,message:string}>  $warnings
     */
    public function __construct(public array $hard = [], public array $warnings = []) {}

    public function hasHardConflicts(): bool
    {
        return $this->hard !== [];
    }

    public function hardCount(): int
    {
        return count($this->hard);
    }
}
