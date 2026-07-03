<?php

namespace App\Actions\Notifications;

use App\Data\Notifications\CreateCommunicationLogData;
use App\Data\Notifications\SendCustomerCommunicationData;
use App\Jobs\DeliverCustomerCommunication;
use App\Models\Member;
use App\Services\Notifications\CommunicationPreferences;
use App\Services\Notifications\CommunicationTemplateRenderer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Send (or suppress) a customer communication email with audit logging (P27).
 */
class SendCustomerCommunication
{
    public function __construct(
        private readonly CommunicationPreferences $preferences,
        private readonly CommunicationTemplateRenderer $templateRenderer,
        private readonly LogCommunication $logCommunication,
    ) {}

    /**
     * @throws AuthorizationException
     */
    public function __invoke(SendCustomerCommunicationData $data): void
    {
        Gate::authorize('sendCommunication', $data->recipient);

        $recipientAddress = $data->recipient->routeNotificationForMail();

        $rendered = $this->templateRenderer->render($data->template, $data->mergeData);

        if ($this->preferences->isOptedOut($data->recipient, $data->notificationTypeKey)) {
            ($this->logCommunication)(new CreateCommunicationLogData(
                notificationTypeKey: $data->notificationTypeKey,
                channel: 'email',
                templateId: $data->template->id,
                notifiableType: Member::class,
                notifiableId: $data->recipient->id,
                subjectType: $data->subject::class,
                subjectId: (int) $data->subject->getKey(),
                recipientAddress: $recipientAddress,
                subjectLine: $rendered['subject'] ?? $data->template->name,
                renderedBody: $rendered['body'],
                status: 'suppressed',
                failureReason: 'Recipient opted out of this notification type.',
            ));

            return;
        }

        $log = ($this->logCommunication)(new CreateCommunicationLogData(
            notificationTypeKey: $data->notificationTypeKey,
            channel: 'email',
            templateId: $data->template->id,
            notifiableType: Member::class,
            notifiableId: $data->recipient->id,
            subjectType: $data->subject::class,
            subjectId: (int) $data->subject->getKey(),
            recipientAddress: $recipientAddress,
            subjectLine: $rendered['subject'] ?? $data->template->name,
            renderedBody: $rendered['body'],
            attachments: $data->document !== null ? ['document:'.$data->document->id] : null,
        ));

        DeliverCustomerCommunication::dispatch(
            communicationLogId: $log->id,
            recipientId: $data->recipient->id,
            templateId: $data->template->id,
            notificationTypeKey: $data->notificationTypeKey,
            mergeData: $data->mergeData,
            documentId: $data->document?->id,
            eyebrow: $data->eyebrow,
            preheader: $data->preheader,
        );
    }
}
