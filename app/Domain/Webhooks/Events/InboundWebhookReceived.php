<?php

declare(strict_types=1);

namespace App\Domain\Webhooks\Events;

use App\Domain\Webhooks\Models\InboundWebhookSource;
use Illuminate\Foundation\Events\Dispatchable;

class InboundWebhookReceived
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public InboundWebhookSource $source,
        public array $payload,
        public int $logId,
    ) {}
}
