{{--
    A single overlap job card, positioned across the window track.
    Status-row segments use track-relative % (same column math as the grid).
--}}
@php
    $colour = $row['colour'];
    $barLeft = (float) $row['left'];
    $barWidth = (float) $row['width'];
    $gapLeft = (int) ($row['gap_left_px'] ?? 0);
    $gapRight = (int) ($row['gap_right_px'] ?? 0);
    $statusRowHeight = $statusRowHeight ?? 18;
    $phaseColours = $phaseColours ?? [];
    $customerBadgeColours = $customerBadgeColours ?? [];
    $tooltip = ($row['number'] ?? ('#'.$row['id']))
        .' · '.$row['state_label'].' · '.$row['status_label']
        .' · '.($row['starts_at']?->format('j M') ?? '')
        .' – '.($row['ends_at']?->format('j M') ?? '');
    $showUrl = route('opportunities.show', $row['id']);
    $contentHeight = 'calc(100% - '.$statusRowHeight.'px)';
@endphp

<div class="absolute left-0 right-0" style="top: {{ $top }}px; height: {{ $height }}px;">
    <a href="{{ $showUrl }}" wire:navigate
       class="absolute block overflow-hidden rounded-none border-y border-black/10 shadow-sm no-underline text-white hover:brightness-105 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-white"
       style="left: calc({{ $barLeft }}% + {{ $gapLeft }}px); width: calc({{ $barWidth }}% - {{ $gapLeft + $gapRight }}px); top: 0; height: {{ $contentHeight }}; background: {{ $colour }};"
       title="{{ $tooltip }}"
       aria-label="{{ ($row['number'] ?? ('#'.$row['id'])).($row['subject'] ? ' — '.$row['subject'] : '') }}">

        @if($row['extends_before'])
            <div class="absolute left-0 top-0 bottom-0 z-20 w-1 bg-[var(--violet,#8b5cf6)]" title="{{ __('Extends beyond view') }}"></div>
        @endif
        @if($row['extends_after'])
            <div class="absolute right-0 top-0 bottom-0 z-20 w-1 bg-[var(--violet,#8b5cf6)]" title="{{ __('Extends beyond view') }}"></div>
        @endif

        <div class="relative z-10 px-1 pt-1">
            {{-- Line 1: number — subject + status / badges --}}
            <div class="flex min-h-[11px] items-start justify-between gap-0.5 leading-none">
                <span class="flex min-w-0 items-center gap-0.5">
                    <flux:icon.document-text class="!size-2.5 shrink-0 opacity-70" />
                    <span class="truncate text-[10px] font-semibold leading-none">
                        {{ $row['number'] ?? ('#'.$row['id']) }}
                        @if($row['subject'])
                            <span class="font-medium opacity-95"> — {{ $row['subject'] }}</span>
                        @endif
                    </span>
                </span>
                <span class="flex shrink-0 items-center gap-0.5">
                    <span class="rounded-sm bg-black/30 px-px py-px text-[6px] font-semibold uppercase tracking-tight leading-none text-white">
                        {{ $row['status_label'] }}
                    </span>
                    @if($row['customer_collecting'])
                        <span class="inline-flex items-center gap-px rounded-sm px-px py-px text-[6px] font-semibold uppercase tracking-tight leading-none text-white"
                              style="background: {{ $customerBadgeColours['collecting'] ?? '#27ae60' }};"
                              title="{{ __('Customer collecting') }}">
                            <flux:icon.truck class="!size-1.5 shrink-0" />
                            {{ __('Coll') }}
                        </span>
                    @endif
                    @if($row['customer_returning'])
                        <span class="inline-flex items-center gap-px rounded-sm px-px py-px text-[6px] font-semibold uppercase tracking-tight leading-none text-white"
                              style="background: {{ $customerBadgeColours['returning'] ?? '#17a2b8' }};"
                              title="{{ __('Customer returning') }}">
                            <flux:icon.truck class="!size-1.5 shrink-0" />
                            {{ __('Ret') }}
                        </span>
                    @endif
                </span>
            </div>

            {{-- Line 2: datetime range --}}
            <div class="flex min-h-[11px] items-center gap-0.5 truncate text-[10px] leading-none opacity-95">
                <flux:icon.clock class="!size-2.5 shrink-0 opacity-70" />
                <span class="truncate">{{ $row['datetime_range'] }}</span>
            </div>

            {{-- Line 3: address --}}
            <div class="flex min-h-[11px] items-center gap-0.5 truncate text-[10px] leading-none opacity-90">
                <flux:icon.map-pin class="!size-2.5 shrink-0 opacity-70" />
                <span class="truncate">{{ $row['address'] }}</span>
            </div>
        </div>
    </a>

    {{-- Status row: snapped to track day columns (not card inset) --}}
    @foreach($row['day_segments'] as $segment)
        @php
            $segLeft = (float) $segment['left'];
            $segWidth = (float) $segment['width'];
            $phase = $segment['phase'];
            $phaseColour = $phaseColours[$phase] ?? 'rgba(255,255,255,0.35)';
            $segmentLabel = match ($phase) {
                'delivery' => __('Del'),
                'collection' => __('Coll'),
                default => __('Use'),
            };
        @endphp
        <div wire:key="seg-{{ $row['id'] }}-{{ $segment['day_index'] }}"
             class="absolute bottom-0 z-10 box-border flex items-center justify-center overflow-hidden border-r border-white/15 text-[6px] font-semibold uppercase tracking-tight text-white"
             style="left: {{ $segLeft }}%; width: {{ $segWidth }}%; height: {{ $statusRowHeight }}px; background-color: {{ $phaseColour }};"
             title="{{ $segment['label'] }}">
            @if($phase !== 'in-use')
                <span class="max-w-full truncate px-px leading-none">{{ $segmentLabel }}</span>
            @endif
        </div>
    @endforeach
</div>
