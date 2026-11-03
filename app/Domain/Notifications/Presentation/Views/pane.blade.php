<div class="app-notifications-mount">
    @auth
        @if($member)
            <aside
                class="app-notifications-pane"
                x-cloak
                x-show="notificationsOpen"
                x-effect="if (notificationsOpen) { $wire.refreshNotifications() }"
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
