<?php

namespace App\Actions\Notifications;

use App\Data\Notifications\CreateCommunicationLogData;
use App\Data\Notifications\TransitionCommunicationLogData;
use App\Models\CommunicationLog;
use App\Services\Notifications\CommunicationLogStatusRegistry;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Persist and transition communication_log rows (P24).
 */
class LogCommunication
{
    public function __construct(
        private readonly CommunicationLogStatusRegistry $statusRegistry,
    ) {}

    public function __invoke(CreateCommunicationLogData $data): CommunicationLog
    {
        if (! $this->statusRegistry->has($data->status)) {
            throw new InvalidArgumentException("Unknown communication log status [{$data->status}].");
        }

        return CommunicationLog::query()->create([
            'notification_type_key' => $data->notificationTypeKey,
            'channel' => $data->channel,
            'provider_id' => $data->providerId,
            'template_id' => $data->templateId,
            'notifiable_type' => $data->notifiableType,
            'notifiable_id' => $data->notifiableId,
            'subject_type' => $data->subjectType,
            'subject_id' => $data->subjectId,
            'recipient_address' => $data->recipientAddress,
            'subject_line' => $data->subjectLine,
            'rendered_body' => $data->renderedBody,
            'attachments' => $data->attachments,
            'status' => $data->status,
            'failure_reason' => $data->failureReason,
            'message_id' => $data->messageId,
            'is_pinned' => $data->isPinned,
            'queued_at' => now(),
        ]);
    }

    public function transition(TransitionCommunicationLogData $data): CommunicationLog
    {
        $log = CommunicationLog::query()->findOrFail($data->communicationLogId);

        $this->statusRegistry->assertCanTransitionTo($log->status, $data->status);

        $attributes = ['status' => $data->status];

        if ($data->failureReason !== null) {
            $attributes['failure_reason'] = $data->failureReason;
        }

        if ($data->messageId !== null) {
            $attributes['message_id'] = $data->messageId;
        }

        $attributes = array_merge($attributes, $this->timestampForStatus($data->status));

        $log->update($attributes);

        return $log->fresh();
    }

    /**
     * @return array<string, Carbon>
     */
    private function timestampForStatus(string $status): array
    {
        return match ($status) {
            'sent' => ['sent_at' => now()],
            'delivered' => ['delivered_at' => now()],
            'opened' => ['opened_at' => now()],
            default => [],
        };
    }
}
