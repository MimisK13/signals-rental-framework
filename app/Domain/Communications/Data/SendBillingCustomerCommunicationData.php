<?php

declare(strict_types=1);

namespace App\Domain\Communications\Data;

use App\Enums\BillingCommunicationKind;
use Spatie\LaravelData\Data;

/**
 * Input for queued billing customer communication delivery (P38).
 */
final class SendBillingCustomerCommunicationData extends Data
{
    public function __construct(
        public BillingCommunicationKind $kind,
        public int $sourceId,
        public int $storeId,
        public ?int $userId = null,
        public bool $explicit = false,
    ) {}
}
