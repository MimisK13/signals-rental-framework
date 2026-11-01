<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Planning;

use Spatie\LaravelData\Data;

final class CrewAvailabilityWindowData extends Data
{
    /**
     * @param  list<array{starts_at:string,ends_at:string}>  $busy
     * @param  list<array{starts_at:string,ends_at:string}>  $free
     */
    public function __construct(public int $crew_member_id, public array $busy, public array $free) {}
}
