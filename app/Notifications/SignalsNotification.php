<?php

namespace App\Notifications;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Abstract base for Signals notification delivery (P24).
 *
 * Channel routing is driven by the three-layer resolver output. Subclasses
 * supply structured content; {@see via()} maps resolved Laravel channels.
 */
abstract class SignalsNotification extends Notification implements ShouldBroadcast, ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $laravelChannels  Resolved Laravel channel names.
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $typeKey,
        public array $laravelChannels,
        public array $payload = [],
    ) {
        $this->onQueue('notifications');
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $this->laravelChannels;
    }

    abstract public function title(): string;

    abstract public function body(): string;

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type_key' => $this->typeKey,
            'title' => $this->title(),
            'body' => $this->body(),
            'payload' => $this->payload,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'type_key' => $this->typeKey,
            'title' => $this->title(),
            'body' => $this->body(),
            'payload' => $this->payload,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->line($this->body());
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [];
    }
}
