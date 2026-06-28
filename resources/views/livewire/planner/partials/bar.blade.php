{{--
    A single overlap job card, positioned across the window track.
--}}
@php
    $withLabel = $withLabel ?? false;
    $colour = $row['colour'];
    $tooltip = ($row['number'] ?? ('#'.$row['id']))
        .' · '.$row['state_label'].' · '.$row['status_label']
        .' · '.($row['starts_at']?->format('j M') ?? '')
        .' – '.($row['ends_at']?->format('j M') ?? '');
    $bandColours = [
        'delivery' => '#3498db',
        'in-use' => '#f39c12',
        'collection' => '#27ae60',
    ];
@endphp

<div class="absolute overflow-hidden rounded-sm border border-black/15 shadow-sm"
     style="left: {{ $row['left'] }}%; width: {{ $row['width'] }}%; top: {{ $top }}px; height: {{ $height }}px; background: {{ $colour }};"
     title="{{ $tooltip }}">

    @if($row['extends_before'])
        <div class="absolute left-0 top-0 bottom-0 z-10 w-1 bg-[var(--violet,#8b5cf6)]" title="{{ __('Extends beyond view') }}"></div>
    @endif
    @if($row['extends_after'])
        <div class="absolute right-0 top-0 bottom-0 z-10 w-1 bg-[var(--violet,#8b5cf6)]" title="{{ __('Extends beyond view') }}"></div>
    @endif

    <div class="flex h-full flex-col justify-between px-2 py-1 text-[10px] font-medium text-white">
        @if($withLabel)
            <div class="min-w-0">
                <a href="{{ route('opportunities.show', $row['id']) }}" wire:navigate
                   class="block truncate font-semibold hover:underline">
                    {{ $row['number'] ?? ('#'.$row['id']) }}
                    @if($row['subject'])
                        <span class="font-normal opacity-90"> — {{ $row['subject'] }}</span>
                    @endif
                </a>
                @if($row['member'])
                    <div class="truncate text-[9px] opacity-90">{{ $row['member'] }}</div>
                @endif
            </div>
            <div class="flex items-center justify-between gap-1">
                <span class="truncate text-[9px] opacity-90">{{ $row['date_range'] }}</span>
                <span class="flex shrink-0 gap-1">
                    @if($row['customer_collecting'])
                        <span class="rounded-sm bg-white/25 px-1 py-px text-[8px] uppercase tracking-wide" title="{{ __('Customer collecting') }}">{{ __('Coll') }}</span>
                    @endif
                    @if($row['customer_returning'])
                        <span class="rounded-sm bg-white/25 px-1 py-px text-[8px] uppercase tracking-wide" title="{{ __('Customer returning') }}">{{ __('Ret') }}</span>
                    @endif
                </span>
            </div>
        @endif
    </div>

    {{-- Phase sub-bars along the bottom --}}
    @foreach($row['bands'] as $band)
        <div wire:key="band-{{ $row['id'] }}-{{ $band['key'] }}"
             class="absolute bottom-0 h-2"
             style="left: {{ $band['left'] }}%; width: {{ $band['width'] }}%; background: {{ $bandColours[$band['key']] ?? 'rgba(255,255,255,0.4)' }};"
             title="{{ $band['label'] }}"></div>
    @endforeach
</div>
