<?php

namespace App\Jobs;

use App\Actions\Notifications\SendBillingCustomerCommunication;
use App\Data\Notifications\SendBillingCustomerCommunicationData;
use App\Enums\BillingCommunicationKind;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Thunk\Verbs\Facades\Verbs;

/**
 * Queued billing customer communication delivery with inline PDF generation (P38).
 */
class SendBillingCustomerCommunicationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(
        public BillingCommunicationKind $kind,
        public int $sourceId,
        public int $storeId,
        public ?int $userId = null,
    ) {
        $this->queue = 'communications';
        $this->afterCommit = true;
    }

    public function handle(SendBillingCustomerCommunication $sendBillingCustomerCommunication): void
    {
        if (Verbs::isReplaying()) {
            return;
        }

        $sendBillingCustomerCommunication(new SendBillingCustomerCommunicationData(
            kind: $this->kind,
            sourceId: $this->sourceId,
            storeId: $this->storeId,
            userId: $this->userId,
        ));
    }
}
