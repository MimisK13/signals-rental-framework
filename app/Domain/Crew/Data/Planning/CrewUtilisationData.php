<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Planning;

use Spatie\LaravelData\Data;

final class CrewUtilisationData extends Data
{
    /** @param list<CrewUtilisationRowData> $rows */
    public function __construct(public array $rows) {}
}
