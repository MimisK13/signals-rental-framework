<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Planning;

use Spatie\LaravelData\Data;

final class CrewWorkHistoryData extends Data
{
    /** @param list<array{assignment_id:int,project_id:int,request_title:string,role:string,status:string,starts_at:string,ends_at:string,approved_minutes:int}> $assignments */
    public function __construct(public int $crew_member_id, public array $assignments) {}
}
