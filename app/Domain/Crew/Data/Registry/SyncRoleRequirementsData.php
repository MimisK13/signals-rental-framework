<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Registry;

use Spatie\LaravelData\Data;

final class SyncRoleRequirementsData extends Data
{
    /** @param list<array<string, mixed>> $requirements */
    public function __construct(public array $requirements) {}
}
