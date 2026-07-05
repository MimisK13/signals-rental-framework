<?php

namespace App\Notifications;

use Illuminate\Broadcasting\Channel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Minimal broadcast payload used by the broadcast channel driver self-test
 * and queued send path (P23 infrastructure only).
 */
class ChannelInfrastructureBroadcastNotification extends Notification implements ShouldBroadcast
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $title,
        public string $body,
        public array $payload = [],
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['broadcast'];
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'title' => $this->title,
            'body' => $this->body,
            'payload' => $this->payload,
            'type' => 'channel.infrastructure.test',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'payload' => $this->payload,
        ];
    }
}
