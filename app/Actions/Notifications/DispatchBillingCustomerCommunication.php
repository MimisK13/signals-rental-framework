<?php

namespace App\Actions\Notifications;

use App\Enums\BillingCommunicationKind;
use App\Jobs\SendBillingCustomerCommunicationJob;
use Thunk\Verbs\Facades\Verbs;

/**
 * Enqueues billing customer communication delivery from domain actions (P38).
 */
class DispatchBillingCustomerCommunication
{
    public function __invoke(
        BillingCommunicationKind $kind,
        int $sourceId,
        int $storeId,
        ?int $userId = null,
    ): void {
        if (Verbs::isReplaying()) {
            return;
        }

        SendBillingCustomerCommunicationJob::dispatch(
            kind: $kind,
            sourceId: $sourceId,
            storeId: $storeId,
            userId: $userId ?? auth()->id(),
        );
    }
}
