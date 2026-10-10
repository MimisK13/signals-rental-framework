<?php

use App\Actions\Notifications\MarkNotificationRead;
use App\Models\Member;
use App\Models\User;
use App\Services\Notifications\NotificationLinkResolver;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component
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
        $this->refreshNotifications();
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
            $data = $notification->data;

            return [
                'id' => $notification->id,
                'title' => (string) ($data['title'] ?? 'Notification'),
                'body' => (string) ($data['body'] ?? ''),
                'url' => $resolver->resolve($notification),
                'read' => $notification->read_at !== null,
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
}; ?>

<div class="app-notifications-mount">
    @auth
        @if($member)
            <aside
                class="app-notifications-pane"
                x-cloak
                x-show="notificationsOpen"
                x-effect="if (notificationsOpen) { $wire.refreshNotifications() }"
                wire:poll.60s="refreshNotifications"
            >
                <div class="app-notifications-header">
                    <span class="app-notifications-title">{{ __('Notifications') }}</span>
                    <div class="flex items-center gap-2">
                        @if($unreadCount > 0)
                            <button
                                type="button"
                                class="text-[10px] text-[var(--text-muted)] hover:text-[var(--text-primary)]"
                                style="font-family: var(--font-display); text-transform: uppercase; letter-spacing: 0.04em;"
                                wire:click="markAllRead"
                            >
                                {{ __('Mark all read') }}
                            </button>
                        @endif
                        <a
                            href="{{ route('settings.notifications') }}"
                            wire:navigate.hover
                            class="text-[var(--text-muted)] hover:text-[var(--text-primary)]"
                            title="{{ __('Notification preferences') }}"
                            aria-label="{{ __('Notification preferences') }}"
                        >
                            <flux:icon.cog-6-tooth class="size-4" />
                        </a>
                    </div>
                </div>

                <div class="app-notifications-body">
                    @forelse($items as $item)
                        <div
                            wire:key="notif-{{ $item['id'] }}"
                            class="border-b border-[var(--border-subtle)] px-4 py-3 {{ $item['read'] ? 'opacity-70' : '' }}"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    @if($item['url'])
                                        <a href="{{ $item['url'] }}" wire:navigate.hover class="block text-[12px] font-semibold text-[var(--text-primary)] hover:underline">
                                            {{ $item['title'] }}
                                        </a>
                                    @else
                                        <div class="text-[12px] font-semibold text-[var(--text-primary)]">{{ $item['title'] }}</div>
                                    @endif
                                    @if($item['body'] !== '')
                                        <p class="mt-0.5 text-[11px] text-[var(--text-muted)] line-clamp-2">{{ $item['body'] }}</p>
                                    @endif
                                    <div class="mt-1 text-[10px] text-[var(--text-muted)]">{{ $item['created_at'] }}</div>
                                </div>
                                @if(! $item['read'])
                                    <button
                                        type="button"
                                        class="s-btn s-btn-ghost s-btn-xs shrink-0"
                                        wire:click="markRead('{{ $item['id'] }}')"
                                        title="{{ __('Mark read') }}"
                                    >
                                        {{ __('Read') }}
                                    </button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="px-4 py-8 text-center text-[12px] text-[var(--text-muted)]">
                            {{ __('No notifications yet.') }}
                        </div>
                    @endforelse
                </div>

            </aside>
        @endif
    @endauth
</div>
