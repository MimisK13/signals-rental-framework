<?php

namespace App\Notifications;

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
