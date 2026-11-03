<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Notifications;

/**
 * Structured notification for config-mapped domain audit events (P25).
 *
 * Title and body are supplied in the dispatch payload until template merge
 * fields land in P26.
 */
class DomainEventNotification extends SignalsNotification
{
    public function title(): string
    {
        return (string) ($this->payload['title'] ?? 'Notification');
    }

    public function body(): string
    {
        return (string) ($this->payload['body'] ?? '');
    }
}
