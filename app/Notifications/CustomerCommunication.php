<?php

namespace App\Notifications;

use App\Actions\Notifications\LogCommunication;
use App\Data\Notifications\TransitionCommunicationLogData;
use App\Jobs\DeliverCustomerCommunication;
use App\Models\CommunicationTemplate;
use App\Models\Document;
use App\Models\Member;
use App\Services\Notifications\CommunicationTemplateRenderer;
use App\Services\Notifications\CustomerCommunicationAttachmentResolver;
use App\Services\Notifications\CustomerCommunicationUnsubscribeUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Customer-facing email rendered from a communication template (P27).
 *
 * Queued via {@see DeliverCustomerCommunication}; direct {@see notify()}
 * calls also honour {@see ShouldQueue} on the communications queue.
 */
class CustomerCommunication extends Notification implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    /**
     * @param  array<string, mixed>  $mergeData
     */
    public function __construct(
        public int $communicationLogId,
        public CommunicationTemplate $template,
        public string $notificationTypeKey,
        public array $mergeData,
        public ?Document $document = null,
        public ?string $eyebrow = null,
        public ?string $preheader = null,
    ) {
        $this->onQueue('communications');
        $this->afterCommit = true;
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        if (! $notifiable instanceof Member) {
            throw new \InvalidArgumentException('CustomerCommunication requires a Member notifiable.');
        }

        $renderer = app(CommunicationTemplateRenderer::class);
        $unsubscribeUrl = app(CustomerCommunicationUnsubscribeUrl::class)
            ->forMember($notifiable, $this->notificationTypeKey);

        $rendered = $renderer->render($this->template, $this->mergeData);

        $message = (new MailMessage)
            ->subject($rendered['subject'] ?? $this->template->name)
            ->view('emails.layouts.signals', [
                'bodyHtml' => $rendered['html'] ?? '',
                'eyebrow' => $this->eyebrow,
                'footerContext' => settings('company.name', config('app.name')),
                'preheader' => $this->preheader ?? ($rendered['subject'] ?? null),
                'unsubscribeUrl' => $unsubscribeUrl,
            ]);

        $attachment = app(CustomerCommunicationAttachmentResolver::class)
            ->resolve($this->document, $this->template);

        if ($attachment !== null) {
            $message->attachData(
                $attachment['content'],
                $attachment['filename'],
                ['mime' => 'application/pdf'],
            );
        }

        return $message;
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('CustomerCommunication permanently failed after all retries', [
            'communication_log_id' => $this->communicationLogId,
            'notification_type_key' => $this->notificationTypeKey,
            'exception' => $exception?->getMessage(),
        ]);

        try {
            app(LogCommunication::class)->transition(new TransitionCommunicationLogData(
                communicationLogId: $this->communicationLogId,
                status: 'failed',
                failureReason: $exception?->getMessage() ?? 'Delivery failed.',
            ));
        } catch (\Throwable $transitionException) {
            report($transitionException);
        }
    }
}
