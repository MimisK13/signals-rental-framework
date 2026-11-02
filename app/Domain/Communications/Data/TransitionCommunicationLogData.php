<?php

declare(strict_types=1);

namespace App\Domain\Communications\Data;

use Spatie\LaravelData\Data;

/**
 * Input for transitioning a communication_log status (P24).
 */
final class TransitionCommunicationLogData extends Data
{
    public function __construct(
        public int $communicationLogId,
        public string $status,
        public ?string $failureReason = null,
        public ?string $messageId = null,
    ) {}
}
