<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Requests;

use Spatie\LaravelData\Data;

final class CloneCrewRequestData extends Data
{
    public function __construct(public string $title) {}
}
