<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Assignments;

use Spatie\LaravelData\Data;

final class ProposeCrewAssignmentData extends Data
{
    public function __construct(public int $crew_member_id, public string $currency_code = 'GBP') {}
}
