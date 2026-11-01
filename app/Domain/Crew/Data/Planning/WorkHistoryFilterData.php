<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Planning;

use Spatie\LaravelData\Data;

final class WorkHistoryFilterData extends Data
{
    public function __construct(public string $starts_at, public string $ends_at) {}
}
