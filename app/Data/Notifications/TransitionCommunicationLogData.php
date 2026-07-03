<?php

namespace App\Data\Notifications;

use Spatie\LaravelData\Data;

/**
 * Input for transitioning a communication_log status (P24).
 */
class TransitionCommunicationLogData extends Data
{
    public function __construct(
        public int $communicationLogId,
        public string $status,
        public ?string $failureReason = null,
        public ?string $messageId = null,
    ) {}
}
