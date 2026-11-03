<?php

declare(strict_types=1);

namespace App\Domain\Crew\Services;

use App\Domain\AuditLog\Events\AuditableEvent;
use App\Domain\Crew\Events\CrewDomainEvent;
use Illuminate\Database\Eloquent\Model;

final class CrewAudit
{
    /** @param array<string, mixed> $context */
    public static function record(string $event, Model $subject, array $context = []): void
    {
        event(new AuditableEvent(
            model: $subject, action: $event, metadata: $context,
            userId: auth()->id(), occurredAt: now(), failOnAuditFailure: true,
        ));

        CrewDomainEvent::dispatch($event, $subject->getMorphClass(), (int) $subject->getKey(), auth()->id());
    }
}
