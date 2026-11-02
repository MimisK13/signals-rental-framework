<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Listeners;

use App\Domain\Quotations\Events\QuotationDomainEvent;
use App\Domain\Quotations\Services\QuotationEventDefinitions;
use App\Services\Api\WebhookService;

final readonly class DispatchQuotationWebhook
{
    public function __construct(private WebhookService $webhooks) {}

    public function handle(QuotationDomainEvent $event): void
    {
        if (in_array($event->name, QuotationEventDefinitions::all(), true)) {
            $this->webhooks->dispatch($event->name, ['id' => $event->quotationId, 'action' => $event->name]);
        }
    }
}
