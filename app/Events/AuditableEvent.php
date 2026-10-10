<?php

namespace App\Events;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

class AuditableEvent
{
    use Dispatchable;

    /**
     * The trailing attribution fields let a caller pass an EXPLICIT actor and
     * occurrence time instead of relying on live auth()/request() context or
     * insert-time timestamps. Event-sourced callers additionally pass a
     * $verbEventId, which acts as the idempotency key so replay re-dispatch
     * inserts nothing twice. Legacy callers omit these fields and retain the
     * original live-auth behaviour.
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public Model $model,
        public string $action,
        public ?array $oldValues = null,
        public ?array $newValues = null,
        public ?array $metadata = null,
        public ?int $verbEventId = null,
        public ?int $userId = null,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
        public ?CarbonInterface $occurredAt = null,
        public bool $failOnAuditFailure = false,
    ) {}
}
