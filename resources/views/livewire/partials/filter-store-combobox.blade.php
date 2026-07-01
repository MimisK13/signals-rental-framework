{{--
    Shared single-store picker for planning filter rows (product bookings).
--}}
@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Store> $stores */
    $storeOptions = $stores
        ->map(static fn (\App\Models\Store $store): array => ['value' => $store->id, 'label' => $store->name])
        ->values()
        ->all();
@endphp
<div class="flex flex-col gap-1">
    <span class="s-field-label">{{ __('Store') }}</span>
    <x-signals.combobox
        wire:key="store-combobox-{{ $storeId }}"
        :value="$storeId ?: null"
        :options="$storeOptions"
        :searchable="true"
        placeholder="{{ __('Select store…') }}"
        class="min-w-44"
        x-on:combobox-selected="$wire.set('storeId', $event.detail.value ? parseInt($event.detail.value, 10) : 0)"
    />
</div>
