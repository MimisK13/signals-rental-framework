{{--
    A single overlap job card, positioned across the window track.
--}}
@php
    $colour = $row['colour'];
    $barLeft = (float) $row['left'];
    $barWidth = (float) $row['width'];
    $statusRowHeight = $statusRowHeight ?? 20;
    $phaseColours = $phaseColours ?? [];
    $tooltip = ($row['number'] ?? ('#'.$row['id']))
        .' · '.$row['state_label'].' · '.$row['status_label']
        .' · '.($row['starts_at']?->format('j M') ?? '')
        .' – '.($row['ends_at']?->format('j M') ?? '');
@endphp

<div class="absolute overflow-hidden rounded-none border-y border-black/10 shadow-sm"
     style="left: {{ $barLeft }}%; width: {{ $barWidth }}%; top: {{ $top }}px; height: {{ $height }}px; background: {{ $colour }};"
     title="{{ $tooltip }}">

    @if($row['extends_before'])
        <div class="absolute left-0 top-0 bottom-0 z-20 w-1 bg-[var(--violet,#8b5cf6)]" title="{{ __('Extends beyond view') }}"></div>
    @endif
    @if($row['extends_after'])
        <div class="absolute right-0 top-0 bottom-0 z-20 w-1 bg-[var(--violet,#8b5cf6)]" title="{{ __('Extends beyond view') }}"></div>
    @endif

    <div class="relative z-10 px-1.5 pt-0.5 text-white" style="height: calc(100% - {{ $statusRowHeight }}px);">
        {{-- Line 1: number — subject + badges --}}
        <div class="flex min-h-[14px] items-start justify-between gap-1 leading-tight">
            <a href="{{ route('opportunities.show', $row['id']) }}" wire:navigate
               class="min-w-0 truncate text-[13px] font-semibold leading-tight hover:underline">
                {{ $row['number'] ?? ('#'.$row['id']) }}
                @if($row['subject'])
                    <span class="font-medium opacity-95"> — {{ $row['subject'] }}</span>
                @endif
            </a>
            <span class="flex shrink-0 gap-0.5">
                @if($row['customer_collecting'])
                    <span class="rounded-sm bg-white/25 px-1 py-px text-[9px] uppercase tracking-wide leading-none" title="{{ __('Customer collecting') }}">{{ __('Coll') }}</span>
                @endif
                @if($row['customer_returning'])
                    <span class="rounded-sm bg-white/25 px-1 py-px text-[9px] uppercase tracking-wide leading-none" title="{{ __('Customer returning') }}">{{ __('Ret') }}</span>
                @endif
            </span>
        </div>

        {{-- Line 2: datetime range --}}
        <div class="min-h-[14px] truncate text-[12px] leading-tight opacity-95">{{ $row['datetime_range'] }}</div>

        {{-- Line 3: address --}}
        <div class="min-h-[14px] truncate text-[12px] leading-tight opacity-90">{{ $row['address'] }}</div>

        {{-- Line 4: spacer row (keeps uniform 4-line body above status band) --}}
        <div class="min-h-[14px] leading-tight" aria-hidden="true"></div>
    </div>

    {{-- Status row: day-snapped segments aligned to window columns --}}
    @foreach($row['day_segments'] as $segment)
        @php
            $segLeft = (($segment['day_index'] * $colWidth - $barLeft) / $barWidth) * 100;
            $segWidth = ($colWidth / $barWidth) * 100;
            $phase = $segment['phase'];
            $phaseColour = $phaseColours[$phase] ?? 'rgba(255,255,255,0.35)';
        @endphp
        <div wire:key="seg-{{ $row['id'] }}-{{ $segment['day_index'] }}"
             class="absolute bottom-0 z-10 flex items-center justify-center overflow-hidden border-r border-white/15 text-[9px] font-semibold uppercase tracking-wide text-white"
             style="left: {{ $segLeft }}%; width: {{ $segWidth }}%; height: {{ $statusRowHeight }}px; background-color: {{ $phaseColour }};"
             title="{{ $segment['label'] }}">
            @if($phase !== 'in-use')
                <span class="rounded-sm bg-black/30 px-1 py-px leading-none">{{ $segment['label'] }}</span>
            @endif
        </div>
    @endforeach
</div>
