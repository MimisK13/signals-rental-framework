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
