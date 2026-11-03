<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Data;

use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelData\Data;

/**
 * Input for programmatic notification dispatch (P24).
 */
class DispatchNotificationData extends Data
{
    /**
     * @param  class-string  $notificationClass
     * @param  list<array<string, mixed>>|null  $recipientRules
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $notificationTypeKey,
        public string $notificationClass,
        public Model $subject,
        public array $payload = [],
        public ?array $recipientRules = null,
        public ?int $actorMemberId = null,
    ) {}
}
