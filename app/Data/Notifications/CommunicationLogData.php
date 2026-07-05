<?php

namespace App\Data\Notifications;

use App\Models\CommunicationLog;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class CommunicationLogData extends Data
{
    /**
     * @param  list<string>|null  $attachments
     */
    public function __construct(
        public int $id,
        public string $notification_type_key,
        public string $channel,
        public ?int $provider_id,
        public ?int $template_id,
        public ?string $notifiable_type,
        public ?int $notifiable_id,
        public ?string $subject_type,
        public ?int $subject_id,
        public ?string $recipient_address,
        public ?string $subject_line,
        public ?string $rendered_body,
        public ?array $attachments,
        public string $status,
        public ?string $failure_reason,
        public ?string $message_id,
        public bool $is_pinned,
        public ?string $queued_at,
        public ?string $sent_at,
        public ?string $delivered_at,
        public ?string $opened_at,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function fromModel(CommunicationLog $log): self
    {
        /** @var Carbon $createdAt */
        $createdAt = $log->created_at;

        /** @var Carbon $updatedAt */
        $updatedAt = $log->updated_at;

        return new self(
            id: $log->id,
            notification_type_key: $log->notification_type_key,
            channel: $log->channel,
            provider_id: $log->provider_id,
            template_id: $log->template_id,
            notifiable_type: self::friendlyType($log->notifiable_type),
            notifiable_id: $log->notifiable_id,
            subject_type: self::friendlyType($log->subject_type),
            subject_id: $log->subject_id,
            recipient_address: $log->recipient_address,
            subject_line: $log->subject_line,
            rendered_body: $log->rendered_body,
            attachments: $log->attachments,
            status: $log->status,
            failure_reason: $log->failure_reason,
            message_id: $log->message_id,
            is_pinned: $log->is_pinned,
            queued_at: $log->queued_at?->toIso8601String(),
            sent_at: $log->sent_at?->toIso8601String(),
            delivered_at: $log->delivered_at?->toIso8601String(),
            opened_at: $log->opened_at?->toIso8601String(),
            created_at: $createdAt->toIso8601String(),
            updated_at: $updatedAt->toIso8601String(),
        );
    }

    private static function friendlyType(?string $type): ?string
    {
        if ($type === null) {
            return null;
        }

        return str(class_basename($type))->snake()->toString();
    }
}
