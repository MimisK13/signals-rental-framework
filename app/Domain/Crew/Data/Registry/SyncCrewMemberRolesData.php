<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Registry;

use Spatie\LaravelData\Data;

final class SyncCrewMemberRolesData extends Data
{
    /** @param list<array<string, mixed>> $roles */
    public function __construct(public array $roles) {}
}
