<?php

declare(strict_types=1);

namespace App\Domain\Communications\Actions;

use App\Domain\Communications\Jobs\SendBillingCustomerCommunication;
use App\Enums\BillingCommunicationKind;
use Thunk\Verbs\Facades\Verbs;

/**
 * Enqueues billing customer communication delivery from domain actions (P38).
 */
final class DispatchBillingCustomerCommunication
{
    public function __invoke(
        BillingCommunicationKind $kind,
        int $sourceId,
        int $storeId,
        ?int $userId = null,
        bool $explicit = false,
    ): void {
        if (Verbs::isReplaying()) {
            return;
        }

        SendBillingCustomerCommunication::dispatch(
            kind: $kind,
            sourceId: $sourceId,
            storeId: $storeId,
            userId: $userId ?? auth()->id(),
            explicit: $explicit,
        );
    }
}
