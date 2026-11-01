<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Registry;

use Spatie\LaravelData\Data;

final class SyncCrewMemberSkillsData extends Data
{
    /** @param list<array<string, mixed>> $skills */
    public function __construct(public array $skills) {}
}
