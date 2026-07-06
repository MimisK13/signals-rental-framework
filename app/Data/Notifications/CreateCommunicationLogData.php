<?php

namespace App\Data\Notifications;

use Spatie\LaravelData\Data;

/**
 * Input for creating a communication_log row (P24).
 */
class CreateCommunicationLogData extends Data
{
    /**
     * @param  list<string>|null  $attachments
     */
    public function __construct(
        public string $notificationTypeKey,
        public string $channel,
        public string $notifiableType,
        public int $notifiableId,
        public string $subjectType,
        public int $subjectId,
        public string $status = 'queued',
        public ?int $providerId = null,
        public ?int $templateId = null,
        public ?string $recipientAddress = null,
        public ?string $subjectLine = null,
        public ?string $renderedBody = null,
        public ?array $attachments = null,
        public ?string $failureReason = null,
        public ?string $messageId = null,
        public bool $isPinned = false,
    ) {}
}
