<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Presentation\Livewire;

use App\Domain\Administration\Models\User;
use App\Domain\Members\Models\Member;
use App\Domain\Notifications\Actions\MarkNotificationRead;
use App\Domain\Notifications\Services\NotificationLinkResolver;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

final class Pane extends Component
{
    public ?Member $member = null;

    public ?int $memberId = null;

    /** @var list<array{id: string, title: string, body: string, url: ?string, read: bool, created_at: string}> */
    public array $items = [];

    public int $unreadCount = 0;

    public function mount(): void
    {
        $user = Auth::user();

        if (! $user instanceof User || $user->member === null) {
            return;
        }

        $this->member = $user->member;
        $this->memberId = $user->member->id;
    }

    /**
     * Real-time refresh when a broadcast notification arrives on the member's
     * private Reverb channel (P24 routeNotificationForBroadcast → member.{id}).
     *
     * @param  array<string, mixed>  $payload
     */
    #[On('echo-private:member.{memberId},.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated')]
    public function onBroadcastNotificationCreated(?string $id = null, ?string $type_key = null, ?string $title = null, ?string $body = null, ?array $payload = null): void
    {
        if ($this->member === null) {
            return;
        }

        $this->refreshNotifications();
    }

    #[On('notifications-stale')]
    public function onNotificationsStale(): void
    {
        $this->refreshNotifications();
    }

    public function refreshNotifications(): void
    {
        if (! isset($this->member)) {
            return;
        }

        $resolver = app(NotificationLinkResolver::class);

        /** @var Collection<int, DatabaseNotification> $notifications */
        $notifications = $this->member->notifications()->latest()->limit(20)->get();

        $this->items = $notifications->map(function (DatabaseNotification $notification) use ($resolver): array {
            /** @var array<string, mixed> $data */
            $data = $notification->getAttribute('data');

            return [
                'id' => $notification->id,
                'title' => (string) ($data['title'] ?? 'Notification'),
                'body' => (string) ($data['body'] ?? ''),
                'url' => $resolver->resolve($notification),
                'read' => $notification->getAttribute('read_at') !== null,
                'created_at' => $notification->created_at?->diffForHumans() ?? '',
            ];
        })->all();

        $this->unreadCount = $this->member->unreadNotifications()->count();
    }

    public function markRead(string $notificationId): void
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return;
        }

        (new MarkNotificationRead)($user, $notificationId);
        $this->refreshNotifications();
        $this->dispatch('notifications-count-stale');
    }

    public function markAllRead(): void
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return;
        }

        (new MarkNotificationRead)->markAll($user);
        $this->refreshNotifications();
        $this->dispatch('notifications-count-stale');
    }

    public function render(): View
    {
        return view('notifications::pane');
    }
}
