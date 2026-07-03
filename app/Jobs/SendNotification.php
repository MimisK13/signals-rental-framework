<?php

namespace App\Jobs;

use App\Actions\Notifications\LogCommunication;
use App\Data\Notifications\CreateCommunicationLogData;
use App\Data\Notifications\TransitionCommunicationLogData;
use App\Models\Member;
use App\Notifications\SignalsNotification;
use App\Services\Notifications\NotificationChannelResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Per-recipient notification delivery with independent retry semantics (P24).
 */
class SendNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    /**
     * @param  list<string>  $logicalChannels
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $notificationClass,
        public string $notificationTypeKey,
        public int $recipientId,
        public string $subjectType,
        public int $subjectId,
        public array $payload = [],
        public array $logicalChannels = [],
    ) {
        $this->onQueue('notifications');

        /*
         * Dispatch only after the surrounding database transaction commits.
         * Without this, a rolled-back transaction would still deliver phantom
         * notifications for mutations that never persisted.
         */
        $this->afterCommit = true;
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new ThrottlesExceptions(3, 5))->backoff(1),
        ];
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function handle(
        NotificationChannelResolver $channelResolver,
        LogCommunication $logCommunication,
    ): void {
        $recipient = Member::query()->find($this->recipientId);

        if ($recipient === null) {
            Log::warning('SendNotification: recipient not found', [
                'recipient_id' => $this->recipientId,
                'notification_type_key' => $this->notificationTypeKey,
            ]);

            return;
        }

        if (! class_exists($this->subjectType)) {
            throw new \RuntimeException("Subject class [{$this->subjectType}] does not exist.");
        }

        /** @var Model|null $subject */
        $subject = $this->subjectType::query()->find($this->subjectId);

        if ($subject === null) {
            throw new \RuntimeException("Subject [{$this->subjectType}:{$this->subjectId}] was not found.");
        }

        if (! is_subclass_of($this->notificationClass, SignalsNotification::class)) {
            throw new \RuntimeException(
                "Notification class [{$this->notificationClass}] must extend SignalsNotification.",
            );
        }

        $laravelChannels = $channelResolver->toLaravelChannels($this->logicalChannels);

        /** @var SignalsNotification $notification */
        $notification = new $this->notificationClass(
            typeKey: $this->notificationTypeKey,
            laravelChannels: $laravelChannels,
            payload: $this->payload,
        );

        $logIds = $this->queueCommunicationLogs($logCommunication, $recipient, $subject, $notification);

        try {
            Notification::sendNow($recipient, $notification);

            foreach ($logIds as $logId) {
                $logCommunication->transition(new TransitionCommunicationLogData(
                    communicationLogId: $logId,
                    status: 'sent',
                ));
            }
        } catch (\Throwable $exception) {
            foreach ($logIds as $logId) {
                $logCommunication->transition(new TransitionCommunicationLogData(
                    communicationLogId: $logId,
                    status: 'failed',
                    failureReason: $exception->getMessage(),
                ));
            }

            throw $exception;
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('SendNotification permanently failed after all retries', [
            'notification_type_key' => $this->notificationTypeKey,
            'recipient_id' => $this->recipientId,
            'subject_type' => $this->subjectType,
            'subject_id' => $this->subjectId,
            'exception' => $exception?->getMessage(),
        ]);
    }

    /**
     * @return list<int>
     */
    private function queueCommunicationLogs(
        LogCommunication $logCommunication,
        Member $recipient,
        Model $subject,
        SignalsNotification $notification,
    ): array {
        $channelResolver = app(NotificationChannelResolver::class);
        $logIds = [];

        foreach ($this->logicalChannels as $logicalChannel) {
            $log = $logCommunication(new CreateCommunicationLogData(
                notificationTypeKey: $this->notificationTypeKey,
                channel: $channelResolver->toLogChannel($logicalChannel),
                notifiableType: Member::class,
                notifiableId: $recipient->id,
                subjectType: $subject::class,
                subjectId: (int) $subject->getKey(),
                recipientAddress: $logicalChannel === 'email'
                    ? $recipient->routeNotificationForMail()
                    : null,
                subjectLine: $notification->title(),
                renderedBody: $notification->body(),
            ));

            $logIds[] = $log->id;
        }

        return $logIds;
    }
}
