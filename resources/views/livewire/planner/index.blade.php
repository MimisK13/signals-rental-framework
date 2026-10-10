<section class="w-full s-avail-grid-page">
    <div class="s-avail-grid-sticky-chrome pointer-events-none sticky top-0 bg-[var(--content-bg)] shadow-sm">
        <div class="pointer-events-auto">
            <x-signals.page-header title="Job Planner">
                <x-slot:meta>
                    <span style="font-family: var(--font-display); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--blue);">Job Planning</span>
                </x-slot:meta>
            </x-signals.page-header>

            <div class="px-6 pb-4 max-md:px-5 max-sm:px-3">
                <div class="s-panel s-planning-filter-panel mb-3">
                    <div class="s-panel-body flex flex-wrap items-end gap-3">
                        @include('livewire.partials.filter-store-tags')

                        <div class="flex flex-col gap-1">
                            <x-dense::field-label>{{ __('Start date') }}</x-dense::field-label>
                            <x-dense::date-picker
                                wire:key="planner-date-{{ $date }}"
                                :value="$date !== '' ? $date : null"
                                placeholder="{{ __('Pick a date') }}"
                                floating
                                class="s-avail-filter-datepicker min-w-[9.5rem]"
                                x-on:input="$wire.set('date', $event.detail)"
                            />
                        </div>

                        <div class="flex items-end gap-1 pb-0.5">
                            <button type="button" wire:click="previousPeriod" class="s-btn s-btn-sm s-btn-ghost" aria-label="{{ __('Previous period') }}">
                                <flux:icon.chevron-left class="!size-4" />
                            </button>
                            <button type="button" wire:click="today" class="s-btn s-btn-sm s-btn-ghost">{{ __('Today') }}</button>
                            <button type="button" wire:click="nextPeriod" class="s-btn s-btn-sm s-btn-ghost" aria-label="{{ __('Next period') }}">
                                <flux:icon.chevron-right class="!size-4" />
                            </button>
                        </div>

                        <div class="flex flex-col gap-1">
                            <x-dense::field-label>{{ __('Zoom') }}</x-dense::field-label>
                            <div class="inline-flex rounded-sm border border-[var(--card-border)] p-0.5">
                                @foreach(['1w' => __('1W'), '2w' => __('2W'), '1m' => __('1M')] as $zoom => $zoomLabel)
                                    <button type="button" wire:click="setZoom('{{ $zoom }}')"
                                            class="s-btn s-btn-sm {{ $viewPeriod === $zoom && $preset === '' ? 's-btn-primary' : 's-btn-ghost' }}"
                                            wire:key="zoom-{{ $zoom }}">{{ $zoomLabel }}</button>
                                @endforeach
                            </div>
                        </div>

                        <div class="flex flex-col gap-1">
                            <x-dense::field-label>{{ __('Presets') }}</x-dense::field-label>
                            <div class="inline-flex flex-wrap gap-1">
                                <button type="button" wire:click="applyPreset('next_30_days')"
                                        class="s-btn s-btn-sm {{ $preset === 'next_30_days' ? 's-btn-primary' : 's-btn-ghost' }}">{{ __('Next 30d') }}</button>
                                <button type="button" wire:click="applyPreset('this_month')"
                                        class="s-btn s-btn-sm {{ $preset === 'this_month' ? 's-btn-primary' : 's-btn-ghost' }}">{{ __('This Month') }}</button>
                                <button type="button" wire:click="applyPreset('next_month')"
                                        class="s-btn s-btn-sm {{ $preset === 'next_month' ? 's-btn-primary' : 's-btn-ghost' }}">{{ __('Next Month') }}</button>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-4 pb-1.5">
                            <label class="inline-flex cursor-pointer items-center gap-2 text-[12px]">
                                <input type="checkbox" wire:model.live="includeOrders" class="sr-only" />
                                <span class="s-badge text-white" style="background: #c0392b;">{{ __('Orders') }}</span>
                                <x-signals.toggle :on="$includeOrders" />
                            </label>
                            <label class="inline-flex cursor-pointer items-center gap-2 text-[12px]">
                                <input type="checkbox" wire:model.live="includeQuotations" class="sr-only" />
                                <span class="s-badge text-white" style="background: var(--amber, #f39c12);">{{ __('Quotes') }}</span>
                                <x-signals.toggle :on="$includeQuotations" />
                            </label>
                        </div>

                        <div class="flex flex-1 flex-col gap-1" style="min-width: 180px;">
                            <x-dense::field-label for="planner-search">{{ __('Search') }}</x-dense::field-label>
                            <x-dense::search-input
                                id="planner-search"
                                model="search"
                                placeholder="{{ __('Subject, number or member') }}"
                            />
                        </div>
                    </div>
                </div>
            </div>

            {{-- Legend --}}
            <div class="mb-2 flex flex-wrap items-center gap-x-4 gap-y-1.5 px-6 text-[11px] text-[var(--text-muted)] max-md:px-5 max-sm:px-3">
                <span class="font-semibold uppercase tracking-wide">{{ __('Key') }}</span>
                @foreach($this->legend as $entry)
                    <span class="flex items-center gap-1.5" wire:key="legend-{{ $loop->index }}">
                        @if(isset($entry['narrow']))
                            <span class="inline-block h-2.5 w-1 rounded-sm" style="background: {{ $entry['colour'] }};"></span>
                        @elseif(isset($entry['border']))
                            <span class="inline-block h-3 w-4 rounded-sm border border-[#ffc107] bg-[#fff3cd]"></span>
                        @else
                            <span class="inline-block h-3 w-4 rounded-sm" style="background: {{ $entry['colour'] }};"></span>
                        @endif
                        @if(isset($entry['icon']))
                            <flux:icon.truck class="!size-3" />
                        @endif
                        {{ $entry['label'] }}
                    </span>
                @endforeach
            </div>

            @if($this->jobsTruncated)
                <p class="mb-2 px-6 text-[11px] text-[var(--text-muted)] max-md:px-5 max-sm:px-3">{{ __('Showing first :count jobs — refine filters to see more.', ['count' => 200]) }}</p>
            @endif

            @if(count($this->rows) > 0)
                <div class="px-6 max-md:px-5 max-sm:px-3">
                    @include('livewire.planner.partials.grid-header')
                </div>
            @endif
        </div>
    </div>

    <div class="px-6 pt-4 pb-4 max-md:px-5 max-sm:px-3">
        @include('livewire.partials.planning-grid-shell', [
            'contentView' => 'livewire.planner.partials.grid',
            'skeletonView' => 'livewire.planner.partials.grid-skeleton',
        ])
    </div>
</section>
