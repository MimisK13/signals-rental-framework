<?php

namespace App\Jobs;

use App\Models\CommunicationTemplate;
use App\Models\Document;
use App\Models\Member;
use App\Notifications\CustomerCommunication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Queued delivery for customer communications with higher timeout budget (P27).
 */
class DeliverCustomerCommunication implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    /**
     * @param  array<string, mixed>  $mergeData
     */
    public function __construct(
        public int $communicationLogId,
        public int $recipientId,
        public int $templateId,
        public string $notificationTypeKey,
        public array $mergeData = [],
        public ?int $documentId = null,
        public ?string $eyebrow = null,
        public ?string $preheader = null,
    ) {
        $this->onQueue('communications');
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

    public function handle(): void
    {
        $recipient = Member::query()->find($this->recipientId);

        if ($recipient === null) {
            Log::warning('DeliverCustomerCommunication: recipient not found', [
                'recipient_id' => $this->recipientId,
                'communication_log_id' => $this->communicationLogId,
            ]);

            return;
        }

        $template = CommunicationTemplate::query()->find($this->templateId);

        if ($template === null) {
            throw new \RuntimeException("Communication template [{$this->templateId}] was not found.");
        }

        $document = $this->documentId !== null
            ? Document::query()->find($this->documentId)
            : null;

        Notification::sendNow($recipient, new CustomerCommunication(
            communicationLogId: $this->communicationLogId,
            template: $template,
            notificationTypeKey: $this->notificationTypeKey,
            mergeData: $this->mergeData,
            document: $document,
            eyebrow: $this->eyebrow,
            preheader: $this->preheader,
        ));
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('DeliverCustomerCommunication permanently failed after all retries', [
            'communication_log_id' => $this->communicationLogId,
            'recipient_id' => $this->recipientId,
            'exception' => $exception?->getMessage(),
        ]);
    }
}
