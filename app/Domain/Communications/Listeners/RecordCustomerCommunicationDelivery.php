<?php

declare(strict_types=1);

namespace App\Domain\Communications\Listeners;

use App\Domain\Communications\Actions\LogCommunication;
use App\Domain\Communications\Data\TransitionCommunicationLogData;
use App\Domain\Communications\Notifications\CustomerCommunication;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Log;

/**
 * Transition communication_log rows when customer emails are delivered (P27).
 */
final class RecordCustomerCommunicationDelivery
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
