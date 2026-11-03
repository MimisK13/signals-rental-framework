@php
    $dayList = $this->windowDays;
    $availabilityIndexUrl = route('availability.index', array_filter([
        'stores' => $storeIds !== [] ? $storeIds : null,
        'from' => $from !== '' ? $from : null,
        'days' => $days,
        'group' => $product->product_group_id,
    ]));
@endphp

<section class="w-full s-avail-grid-page s-avail-grid-page--bookings">
    <div class="s-avail-grid-sticky-chrome sticky top-0 bg-[var(--content-bg)] shadow-sm">
        <x-dense::page-header title="{{ __('Product Bookings') }}">
            <x-slot:meta>
                <span style="font-family: var(--font-display); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--blue);">{{ __('Job Planning') }}</span>
            </x-slot:meta>
            <x-slot:actions>
                <flux:button variant="ghost" href="{{ $availabilityIndexUrl }}" wire:navigate.hover icon="arrow-left">
                    {{ __('Equipment Availability') }}
                </flux:button>
            </x-slot:actions>
        </x-dense::page-header>

        <div class="px-6 pb-4 max-md:px-5 max-sm:px-3">
            <div class="s-panel s-planning-filter-panel">
                <div class="s-panel-body flex flex-wrap items-end gap-3">
                    @include('livewire.partials.filter-store-tags')

                    <div class="flex flex-col gap-1">
                        <span class="s-field-label">{{ __('Product') }}</span>
                        <div class="s-select-display min-w-52" title="{{ $product->name }}">
                            <span class="s-select-display-text">{{ $product->name }}</span>
                            <a href="{{ route('products.show', $product) }}"
                               target="_blank"
                               rel="noopener noreferrer"
                               class="s-select-display-action"
                               title="{{ __('Product details') }}">
                                <flux:icon.information-circle class="!size-3.5" />
                            </a>
                        </div>
                    </div>

                    <div class="flex flex-col gap-1">
                        <span class="s-field-label">{{ __('Start date') }}</span>
                        <x-signals.datepicker
                            wire:key="bookings-from-{{ $from }}"
                            :value="$from !== '' ? $from : null"
                            placeholder="{{ __('Pick a date') }}"
                            floating
                            class="s-avail-filter-datepicker min-w-[9.5rem]"
                            x-on:input="$wire.set('from', $event.detail)"
                        />
                    </div>

                    <div class="flex flex-col gap-1">
                        <span class="s-field-label">{{ __('Days') }}</span>
                        <select wire:model.live="days" class="s-select min-w-[9.5rem]" aria-label="{{ __('Days period') }}">
                            @foreach($this->periodOptions() as $option)
                                <option value="{{ $option }}">{{ $option }} {{ __('days') }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($this->resolvedStoreIds() === [])
        <div class="px-6 max-md:px-5 max-sm:px-3">
            <x-dense::empty-state
                title="{{ __('No stores available') }}"
                description="{{ __('Add a store to view bookings.') }}">
                <x-slot:icon><flux:icon.building-storefront class="!size-7" /></x-slot:icon>
            </x-dense::empty-state>
        </div>
    @else
        <div class="px-6 pt-4 pb-4 max-md:px-5 max-sm:px-3">
            @include('livewire.partials.planning-grid-shell', [
                'contentView' => 'availability::partials.bookings-content',
                'skeletonView' => 'availability::partials.grid-skeleton',
            ])
        </div>
    @endif
</section>

