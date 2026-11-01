<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Requests;

use Spatie\LaravelData\Data;

final class SyncLineRequirementsData extends Data
{
    /** @param list<array{requirement_type:string,crew_skill_id?:int|null,crew_qualification_id?:int|null,is_mandatory:bool,min_proficiency?:string|null}> $requirements */
    public function __construct(public array $requirements) {}
}
