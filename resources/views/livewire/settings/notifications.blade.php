<?php

use App\Actions\Notifications\SaveMemberNotificationPreferences;
use App\Data\Notifications\SaveMemberNotificationPreferencesData;
use App\Models\NotificationType;
use App\Models\User;
use App\Services\Notifications\MemberNotificationPreferenceCatalog;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Notifications')] class extends Component
{
    /** @var array<int, array{channels: list<string>, is_muted: bool}> */
    public array $preferences = [];

    public function mount(MemberNotificationPreferenceCatalog $catalog): void
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(403);
        }

        foreach ($catalog->forUser($user) as $row) {
            /** @var NotificationType $type */
            $type = $row['type'];

            $this->preferences[$type->id] = [
                'channels' => $row['member_channels'],
                'is_muted' => $row['is_muted'],
            ];
        }
    }

    public function save(MemberNotificationPreferenceCatalog $catalog): void
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(403);
        }

        $allowedTypeIds = $catalog->forUser($user)
            ->map(fn (array $row): int => $row['type']->id)
            ->all();

        foreach ($this->preferences as $typeId => $state) {
            if (! in_array((int) $typeId, $allowedTypeIds, true)) {
                continue;
            }

            app(SaveMemberNotificationPreferences::class)($user, new SaveMemberNotificationPreferencesData(
                targetUserId: $user->id,
                notificationTypeId: (int) $typeId,
                channels: $state['is_muted'] ? null : $state['channels'],
                isMuted: $state['is_muted'],
            ));
        }

        $this->dispatch('toast', type: 'success', message: __('Notification preferences saved.'));
    }

    /**
     * @return array<string, mixed>
     */
    public function with(MemberNotificationPreferenceCatalog $catalog): array
    {
        $user = Auth::user();

        return [
            'catalog' => $user instanceof User ? $catalog->forUser($user)->groupBy(fn (array $row): string => $row['type']->category) : collect(),
            'channelLabels' => [
                'in_app' => 'In-app',
                'email' => 'Email',
                'sms' => 'SMS',
                'slack' => 'Slack',
                'teams' => 'Teams',
            ],
        ];
    }
}; ?>

<section class="w-full">
    <x-settings.layout
        heading="Notifications"
        subheading="Choose how you receive alerts. You can only disable channels your organisation has enabled."
    >
        <form wire:submit="save" class="space-y-8">
            @forelse($catalog as $category => $rows)
                <x-signals.form-section :title="$category">
                    <div class="divide-y divide-[var(--border-subtle)]">
                        @foreach($rows as $row)
                            @php
                                $type = $row['type'];
                                $typeId = $type->id;
                            @endphp
                            <div wire:key="pref-type-{{ $typeId }}" class="py-4 first:pt-0 last:pb-0">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <div class="text-[13px] font-medium text-[var(--text-primary)]">{{ $type->name }}</div>
                                        @if($type->description)
                                            <p class="mt-0.5 text-[11px] text-[var(--text-muted)]">{{ $type->description }}</p>
                                        @endif
                                    </div>
                                    <label
                                        class="flex cursor-pointer items-center gap-2 text-[11px] text-[var(--text-muted)]"
                                        x-data="{ checked: @js($preferences[$typeId]['is_muted'] ?? false) }"
                                    >
                                        <input
                                            type="checkbox"
                                            class="hidden"
                                            wire:model.live="preferences.{{ $typeId }}.is_muted"
                                            x-on:change="checked = $el.checked"
                                        />
                                        <x-signals.checkbox x-bind:class="checked && 'checked'" />
                                        {{ __('Mute') }}
                                    </label>
                                </div>

                                @if(! ($preferences[$typeId]['is_muted'] ?? false))
                                    <div class="mt-3 flex flex-wrap gap-4">
                                        @foreach($row['tenant_channels'] as $channel)
                                            <label
                                                wire:key="pref-{{ $typeId }}-{{ $channel }}"
                                                class="flex cursor-pointer items-center gap-2 text-[12px]"
                                                x-data="{ checked: @js(in_array($channel, $preferences[$typeId]['channels'] ?? [], true)) }"
                                            >
                                                <input
                                                    type="checkbox"
                                                    class="hidden"
                                                    value="{{ $channel }}"
                                                    wire:model="preferences.{{ $typeId }}.channels"
                                                    x-on:change="checked = $el.checked"
                                                />
                                                <x-signals.checkbox x-bind:class="checked && 'checked'" />
                                                {{ $channelLabels[$channel] ?? ucfirst($channel) }}
                                            </label>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </x-signals.form-section>
            @empty
                <x-signals.form-section title="Preferences">
                    <p class="text-[12px] text-[var(--text-muted)]">{{ __('No configurable notification types are available.') }}</p>
                </x-signals.form-section>
            @endforelse

            @if($catalog->isNotEmpty())
                <div class="flex items-center gap-4">
                    <flux:button variant="primary" type="submit" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="save">{{ __('Save') }}</span>
                        <span wire:loading wire:target="save">{{ __('Saving…') }}</span>
                    </flux:button>
                </div>
            @endif
        </form>
    </x-settings.layout>
</section>
