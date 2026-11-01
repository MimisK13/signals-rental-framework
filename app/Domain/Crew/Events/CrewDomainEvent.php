<?php

declare(strict_types=1);

namespace App\Domain\Crew\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class CrewDomainEvent implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public string $name, public string $subject_type, public int $subject_id, public ?int $actor_id)
    {
        //
    }
}
