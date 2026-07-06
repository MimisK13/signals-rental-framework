<?php

namespace App\Events;

use App\Models\InboundWebhookSource;
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
