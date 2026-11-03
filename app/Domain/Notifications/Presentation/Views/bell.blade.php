<div>
    @if($memberId)
        <button
            class="header-icon-btn"
            title="{{ __('Notifications') }}"
            aria-label="{{ __('Notifications') }}"
            :aria-expanded="notificationsOpen"
            x-on:click="notificationsOpen = !notificationsOpen"
            :class="{ 'active': notificationsOpen }"
            type="button"
            wire:poll.60s="refreshCount"
        >
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 6a4 4 0 0 1 8 0c0 3 1.5 4.5 2 5H2c.5-.5 2-2 2-5z"/><path d="M6 11v.5a2 2 0 0 0 4 0V11"/></svg>
            @if($unreadCount > 0)
                <span class="notification-badge" wire:key="notif-badge">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
            @endif
        </button>
    @endif
</div>
