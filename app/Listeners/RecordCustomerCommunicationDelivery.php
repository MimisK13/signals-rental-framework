<?php

namespace App\Listeners;

use App\Actions\Notifications\LogCommunication;
use App\Data\Notifications\TransitionCommunicationLogData;
use App\Notifications\CustomerCommunication;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Log;

/**
 * Transition communication_log rows when customer emails are delivered (P27).
 */
class RecordCustomerCommunicationDelivery
{
    public function __construct(
        private readonly LogCommunication $logCommunication,
    ) {}

    public function handle(NotificationSent $event): void
    {
        if (! $event->notification instanceof CustomerCommunication) {
            return;
        }

        try {
            $this->logCommunication->transition(new TransitionCommunicationLogData(
                communicationLogId: $event->notification->communicationLogId,
                status: 'sent',
            ));
        } catch (\Throwable $exception) {
            Log::warning('RecordCustomerCommunicationDelivery: could not transition log', [
                'communication_log_id' => $event->notification->communicationLogId,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
