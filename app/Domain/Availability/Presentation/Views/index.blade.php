<section class="w-full s-avail-grid-page s-avail-grid-page--index">
    <div class="s-avail-grid-sticky-chrome sticky top-0 bg-[var(--content-bg)] shadow-sm">
        <x-dense::page-header title="Equipment Availability">
            <x-slot:meta>
                <span style="font-family: var(--font-display); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--blue);">Job Planning</span>
            </x-slot:meta>
        </x-dense::page-header>

        <div class="px-6 pb-4 max-md:px-5 max-sm:px-3">
            <div class="s-panel s-planning-filter-panel">
                <div class="s-panel-body flex flex-wrap items-end gap-3">
                    @include('livewire.partials.filter-store-tags')

                    <div class="flex flex-col gap-1">
                        <span class="s-field-label">{{ __('Product Group') }}</span>
                        <select wire:model.live="groupId" class="s-select min-w-52" aria-label="{{ __('Product group') }}">
                            <option value="">{{ __('Select a group…') }}</option>
                            @foreach($productGroups as $group)
                                <option value="{{ $group['id'] }}">{{ $group['name'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex flex-col gap-1">
                        <span class="s-field-label">{{ __('Product type') }}</span>
                        <select wire:model.live="productType" class="s-select min-w-32" aria-label="{{ __('Product type') }}">
                            <option value="">{{ __('All') }}</option>
                            @foreach($productTypeOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex flex-col gap-1">
                        <span class="s-field-label">{{ __('Start date') }}</span>
                        <x-signals.datepicker
                            wire:key="avail-from-{{ $from }}"
                            :value="$from !== '' ? $from : null"
                            placeholder="{{ __('Pick a date') }}"
                            floating
                            class="s-avail-filter-datepicker min-w-[9.5rem]"
                            x-on:input="$wire.set('from', $event.detail)"
                        />
                    </div>

                    <div class="flex flex-col gap-1">
                        <span class="s-field-label">{{ __('Days') }}</span>
                        <select wire:model.live="daysPeriod" class="s-select min-w-[9.5rem]" aria-label="{{ __('Days period') }}">
                            @foreach($periodOptions as $option)
                                <option value="{{ $option }}">{{ $option }} {{ __('days') }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex flex-wrap items-center gap-4 pb-1.5">
                        <label class="inline-flex cursor-pointer items-center gap-2 text-[12px]">
                            <input type="checkbox" wire:model.live="shortagesOnly" class="sr-only" />
                            <span>{{ __('Shortages only') }}</span>
                            <x-signals.toggle :on="$shortagesOnly" />
                        </label>
                        <label class="inline-flex cursor-pointer items-center gap-2 text-[12px]">
                            <input type="checkbox" wire:model.live="warningsOnly" class="sr-only" />
                            <span>{{ __('Warnings only') }}</span>
                            <x-signals.toggle :on="$warningsOnly" />
                        </label>
                        <label class="inline-flex cursor-not-allowed items-center gap-2 text-[12px] opacity-50"
                               title="{{ __('Coming in a future update — the read model does not yet support a quote-phase filter.') }}">
                            <input type="checkbox" wire:model.live="includeQuotes" class="sr-only" disabled />
                            <span>{{ __('Include quotes') }}</span>
                            <x-signals.toggle :on="$includeQuotes" />
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="px-6 pt-4 pb-4 max-md:px-5 max-sm:px-3">
        @include('livewire.partials.planning-grid-shell', [
            'contentView' => 'availability::partials.group',
            'skeletonView' => 'availability::partials.grid-skeleton',
        ])
    </div>
</section>

