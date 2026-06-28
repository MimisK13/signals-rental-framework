{{--
    A single overlap job card, positioned across the window track.
--}}
@php
    $colour = $row['colour'];
    $barLeft = (float) $row['left'];
    $barWidth = (float) $row['width'];
    $gapLeft = (int) ($row['gap_left_px'] ?? 0);
    $gapRight = (int) ($row['gap_right_px'] ?? 0);
    $statusRowHeight = $statusRowHeight ?? 20;
    $phaseColours = $phaseColours ?? [];
    $customerBadgeColours = $customerBadgeColours ?? [];
    $tooltip = ($row['number'] ?? ('#'.$row['id']))
        .' · '.$row['state_label'].' · '.$row['status_label']
        .' · '.($row['starts_at']?->format('j M') ?? '')
        .' – '.($row['ends_at']?->format('j M') ?? '');
    $showUrl = route('opportunities.show', $row['id']);
@endphp

<a href="{{ $showUrl }}" wire:navigate
   class="absolute block overflow-hidden rounded-none border-y border-black/10 shadow-sm no-underline text-white hover:brightness-105 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-white"
   style="left: calc({{ $barLeft }}% + {{ $gapLeft }}px); width: calc({{ $barWidth }}% - {{ $gapLeft + $gapRight }}px); top: {{ $top }}px; height: {{ $height }}px; background: {{ $colour }};"
   title="{{ $tooltip }}"
   aria-label="{{ ($row['number'] ?? ('#'.$row['id'])).($row['subject'] ? ' — '.$row['subject'] : '') }}">

    @if($row['extends_before'])
        <div class="absolute left-0 top-0 bottom-0 z-20 w-1 bg-[var(--violet,#8b5cf6)]" title="{{ __('Extends beyond view') }}"></div>
    @endif
    @if($row['extends_after'])
        <div class="absolute right-0 top-0 bottom-0 z-20 w-1 bg-[var(--violet,#8b5cf6)]" title="{{ __('Extends beyond view') }}"></div>
    @endif

    <div class="relative z-10 px-1.5 pt-0.5" style="height: calc(100% - {{ $statusRowHeight }}px);">
        {{-- Line 1: number — subject + badges --}}
        <div class="flex min-h-[14px] items-start justify-between gap-1 leading-tight">
            <span class="flex min-w-0 items-center gap-1">
                <flux:icon.document-text class="!size-3 shrink-0 opacity-70" />
                <span class="truncate text-[13px] font-semibold leading-tight">
                    {{ $row['number'] ?? ('#'.$row['id']) }}
                    @if($row['subject'])
                        <span class="font-medium opacity-95"> — {{ $row['subject'] }}</span>
                    @endif
                </span>
            </span>
            <span class="flex shrink-0 gap-0.5">
                @if($row['customer_collecting'])
                    <span class="inline-flex items-center gap-0.5 rounded-sm px-1 py-px text-[8px] font-semibold uppercase tracking-wide leading-none text-white"
                          style="background: {{ $customerBadgeColours['collecting'] ?? '#27ae60' }};"
                          title="{{ __('Customer collecting') }}">
                        <flux:icon.truck class="!size-2.5 shrink-0" />
                        {{ __('Coll') }}
                    </span>
                @endif
                @if($row['customer_returning'])
                    <span class="inline-flex items-center gap-0.5 rounded-sm px-1 py-px text-[8px] font-semibold uppercase tracking-wide leading-none text-white"
                          style="background: {{ $customerBadgeColours['returning'] ?? '#17a2b8' }};"
                          title="{{ __('Customer returning') }}">
                        <flux:icon.truck class="!size-2.5 shrink-0" />
                        {{ __('Ret') }}
                    </span>
                @endif
            </span>
        </div>

        {{-- Line 2: datetime range --}}
        <div class="flex min-h-[14px] items-center gap-1 truncate text-[12px] leading-tight opacity-95">
            <flux:icon.clock class="!size-3 shrink-0 opacity-70" />
            <span class="truncate">{{ $row['datetime_range'] }}</span>
        </div>

        {{-- Line 3: address --}}
        <div class="flex min-h-[14px] items-center gap-1 truncate text-[12px] leading-tight opacity-90">
            <flux:icon.map-pin class="!size-3 shrink-0 opacity-70" />
            <span class="truncate">{{ $row['address'] }}</span>
        </div>
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
             class="absolute bottom-0 z-10 flex items-center justify-center overflow-hidden border-r border-white/15 text-[7px] font-semibold uppercase tracking-tight text-white"
             style="left: {{ $segLeft }}%; width: {{ $segWidth }}%; height: {{ $statusRowHeight }}px; background-color: {{ $phaseColour }};"
             title="{{ $segment['label'] }}">
            @if($phase !== 'in-use')
                <span class="max-w-full truncate px-0.5 leading-none">{{ $segment['label'] }}</span>
            @endif
        </div>
    @endforeach
</a>
