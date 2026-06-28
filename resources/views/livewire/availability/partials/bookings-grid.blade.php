{{--
    Product bookings grid body — shares table chrome with the group availability view.
--}}
@php
    $dayList = $this->windowDays;
    $summary = $this->summaryRows;
    $opportunityRows = $this->opportunityRows;
    $assetRows = $this->assetRows;
    $colspan = count($dayList) + 2;
@endphp

@include('livewire.availability.partials.grid-shell-open')
    @include('livewire.availability.partials.grid-header', [
        'dayList' => $dayList,
        'labelColumn' => '',
        'keyPrefix' => 'bhead',
    ])
    <tbody>
        <tr wire:key="summary-held" class="s-avail-group-row--summary">
            @include('livewire.availability.partials.grid-label', [
                'label' => __('Held'),
                'muted' => true,
            ])
            @foreach($summary['held'] as $cell)
                @include('livewire.availability.partials.grid-day-cell', [
                    'mode' => 'summary',
                    'date' => $cell['date'],
                    'cell' => $cell,
                    'wireKey' => 'held-'.$cell['date'],
                ])
            @endforeach
            @include('livewire.availability.partials.grid-nav-spacer')
        </tr>
        <tr wire:key="summary-available" class="s-avail-group-row--summary">
            @include('livewire.availability.partials.grid-label', [
                'label' => __('Available'),
            ])
            @foreach($summary['available'] as $cell)
                @include('livewire.availability.partials.grid-day-cell', [
                    'mode' => 'availability',
                    'date' => $cell['date'],
                    'cell' => $cell,
                    'wireKey' => 'avail-'.$cell['date'],
                ])
            @endforeach
            @include('livewire.availability.partials.grid-nav-spacer')
        </tr>
        <tr wire:key="summary-post-rent" class="s-avail-group-row--summary">
            @include('livewire.availability.partials.grid-label', [
                'label' => __('Post rent'),
                'muted' => true,
            ])
            @foreach($summary['post_rent'] as $cell)
                @include('livewire.availability.partials.grid-day-cell', [
                    'mode' => 'summary',
                    'date' => $cell['date'],
                    'cell' => $cell,
                    'wireKey' => 'post-'.$cell['date'],
                ])
            @endforeach
            @include('livewire.availability.partials.grid-nav-spacer')
        </tr>

        @include('livewire.availability.partials.grid-section', [
            'wireKey' => 'section-opportunities',
            'dataColspan' => count($dayList),
            'label' => __('Opportunities'),
        ])
        @forelse($opportunityRows as $row)
            <tr wire:key="opp-{{ $row['opportunity_id'] }}-{{ $loop->index }}" class="s-avail-group-row--demand">
                @include('livewire.availability.partials.grid-label', [
                    'label' => $row['label'],
                    'url' => $row['url'],
                ])
                @foreach($row['cells'] as $cell)
                    @include('livewire.availability.partials.grid-day-cell', [
                        'mode' => 'demand',
                        'date' => $cell['date'],
                        'cell' => $cell,
                        'wireKey' => 'opp-'.$row['opportunity_id'].'-'.$cell['date'],
                    ])
                @endforeach
                @include('livewire.availability.partials.grid-nav-spacer')
            </tr>
        @empty
            <tr wire:key="opp-empty">
                <td colspan="{{ $colspan }}" class="px-3 py-2 text-[12px] text-[var(--text-muted)]">{{ __('No opportunity bookings in this window.') }}</td>
            </tr>
        @endforelse

        @include('livewire.availability.partials.grid-section', [
            'wireKey' => 'section-assets',
            'dataColspan' => count($dayList),
            'label' => __('Assets'),
        ])
        @forelse($assetRows as $row)
            <tr wire:key="asset-{{ $row['asset_id'] }}" class="s-avail-group-row--demand">
                @include('livewire.availability.partials.grid-label', [
                    'label' => $row['label'],
                ])
                @foreach($row['cells'] as $cell)
                    @include('livewire.availability.partials.grid-day-cell', [
                        'mode' => 'demand',
                        'date' => $cell['date'],
                        'cell' => $cell,
                        'wireKey' => 'asset-'.$row['asset_id'].'-'.$cell['date'],
                    ])
                @endforeach
                @include('livewire.availability.partials.grid-nav-spacer')
            </tr>
        @empty
            <tr wire:key="asset-empty">
                <td colspan="{{ $colspan }}" class="px-3 py-2 text-[12px] text-[var(--text-muted)]">{{ __('No serialised assets at this store.') }}</td>
            </tr>
        @endforelse
    </tbody>
@include('livewire.availability.partials.grid-shell-close')
