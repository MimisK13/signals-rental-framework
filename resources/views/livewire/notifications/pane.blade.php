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
    }

    public function markAllRead(): void
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return;
        }

        (new MarkNotificationRead)->markAll($user);
        $this->refreshNotifications();
    }
}; ?>

<div class="app-notifications-mount">
    @auth
        @if($member)
            <aside
                class="app-notifications-pane"
                :class="{ 'open': notificationsOpen, 'ready': notificationsReady }"
                x-effect="if (notificationsOpen) { $wire.refreshNotifications() }"
                wire:poll.60s="refreshNotifications"
            >
                <div class="app-notifications-header">
                    <span class="app-notifications-title">{{ __('Notifications') }}</span>
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
                                        <a href="{{ $item['url'] }}" wire:navigate class="block text-[12px] font-semibold text-[var(--text-primary)] hover:underline">
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

                <div class="app-notifications-footer border-t border-[var(--border-subtle)] px-4 py-2">
                    <a href="{{ route('settings.notifications') }}" wire:navigate class="text-[11px] text-[var(--text-muted)] hover:text-[var(--text-primary)]">
                        {{ __('Notification preferences') }}
                    </a>
                </div>

                <button
                    class="notifications-toggle"
                    x-on:click="notificationsOpen = !notificationsOpen"
                    :aria-label="notificationsOpen ? 'Collapse notifications' : 'Expand notifications'"
                    type="button"
                >
                    <svg class="sidebar-toggle-icon" :class="{ 'rotate-180': notificationsOpen }" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M6 3l5 5-5 5"/>
                    </svg>
                </button>
            </aside>
        @endif
    @endauth
</div>
