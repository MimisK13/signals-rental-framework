<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Notifications;

/**
 * Concrete notification used by the P24 dispatch pipeline tests and examples.
 */
class TestPipelineNotification extends SignalsNotification
{
    public function title(): string
    {
        return (string) ($this->payload['title'] ?? 'Test notification');
    }

    public function body(): string
    {
        return (string) ($this->payload['body'] ?? 'Test notification body.');
    }
}
