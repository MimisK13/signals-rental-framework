{{--
    A single overlap job card, positioned across the window track.
--}}
@php
    $colour = $row['colour'];
    $barLeft = (float) $row['left'];
    $barWidth = (float) $row['width'];
    $tooltip = ($row['number'] ?? ('#'.$row['id']))
        .' · '.$row['state_label'].' · '.$row['status_label']
        .' · '.($row['starts_at']?->format('j M') ?? '')
        .' – '.($row['ends_at']?->format('j M') ?? '');
    $phaseColours = [
        'delivery' => '#3498db',
        'in-use' => '#f39c12',
        'collection' => '#27ae60',
    ];
@endphp

<div class="absolute overflow-hidden rounded-sm border border-black/15 shadow-sm"
     style="left: {{ $barLeft }}%; width: {{ $barWidth }}%; top: {{ $top }}px; height: {{ $height }}px; background: {{ $colour }};"
     title="{{ $tooltip }}">

    @if($row['extends_before'])
        <div class="absolute left-0 top-0 bottom-0 z-20 w-1 bg-[var(--violet,#8b5cf6)]" title="{{ __('Extends beyond view') }}"></div>
    @endif
    @if($row['extends_after'])
        <div class="absolute right-0 top-0 bottom-0 z-20 w-1 bg-[var(--violet,#8b5cf6)]" title="{{ __('Extends beyond view') }}"></div>
    @endif

    <div class="relative z-10 flex h-full flex-col px-2 pb-5 pt-1 text-[10px] font-medium text-white">
        {{-- Line 1: number — subject + badges --}}
        <div class="flex min-w-0 items-start justify-between gap-2">
            <a href="{{ route('opportunities.show', $row['id']) }}" wire:navigate
               class="min-w-0 truncate font-semibold hover:underline">
                {{ $row['number'] ?? ('#'.$row['id']) }}
                @if($row['subject'])
                    <span class="font-normal opacity-95"> — {{ $row['subject'] }}</span>
                @endif
            </a>
            <span class="flex shrink-0 gap-1">
                @if($row['customer_collecting'])
                    <span class="rounded-sm bg-white/25 px-1 py-px text-[8px] uppercase tracking-wide" title="{{ __('Customer collecting') }}">{{ __('Coll') }}</span>
                @endif
                @if($row['customer_returning'])
                    <span class="rounded-sm bg-white/25 px-1 py-px text-[8px] uppercase tracking-wide" title="{{ __('Customer returning') }}">{{ __('Ret') }}</span>
                @endif
            </span>
        </div>

        {{-- Line 2: datetime range --}}
        <div class="truncate text-[9px] opacity-95">{{ $row['datetime_range'] }}</div>

        {{-- Line 3: address (or location name fallback) --}}
        <div class="truncate text-[9px] opacity-90">{{ $row['address'] }}</div>
    </div>

    {{-- Line 4: day-snapped state row aligned to window columns --}}
    @foreach($row['day_segments'] as $segment)
        @php
            $segLeft = (($segment['day_index'] * $colWidth - $barLeft) / $barWidth) * 100;
            $segWidth = ($colWidth / $barWidth) * 100;
            $phase = $segment['phase'];
            $phaseColour = $phaseColours[$phase] ?? 'rgba(255,255,255,0.35)';
        @endphp
        <div wire:key="seg-{{ $row['id'] }}-{{ $segment['day_index'] }}"
             class="absolute bottom-0 z-10 flex h-5 items-center justify-center overflow-hidden border-r border-white/10 text-[8px] font-semibold uppercase tracking-wide text-white"
             style="left: {{ $segLeft }}%; width: {{ $segWidth }}%; background: {{ $phaseColour }};"
             title="{{ $segment['label'] }}">
            @if($phase !== 'in-use')
                <span class="rounded-sm bg-black/25 px-1 py-px leading-none">{{ $segment['label'] }}</span>
            @endif
        </div>
    @endforeach
</div>
