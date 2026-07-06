<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use RuntimeException;

/**
 * Test notification that fails mail delivery for a configured recipient ID.
 */
class FailingTestPipelineNotification extends TestPipelineNotification
{
    public function toMail(object $notifiable): MailMessage
    {
        $failingRecipientId = $this->payload['failing_recipient_id'] ?? null;

        if ($failingRecipientId !== null && (int) $notifiable->id === (int) $failingRecipientId) {
            throw new RuntimeException('Simulated mail failure');
        }

        return parent::toMail($notifiable);
    }
}
