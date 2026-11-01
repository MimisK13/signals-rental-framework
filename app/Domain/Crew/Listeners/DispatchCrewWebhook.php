<?php

declare(strict_types=1);

namespace App\Domain\Crew\Listeners;

use App\Domain\Crew\Events\CrewDomainEvent;
use App\Domain\Crew\Services\CrewEventDefinitions;
use App\Services\Api\WebhookService;

final class DispatchCrewWebhook
{
    public function __construct(private WebhookService $webhooks)
    {
        //
    }

    public function handle(CrewDomainEvent $event): void
    {
        if (in_array($event->name, CrewEventDefinitions::WEBHOOKS, true)) {
            $this->webhooks->dispatch($event->name, ['id' => $event->subject_id, 'action' => $event->name]);
        }
    }
}
