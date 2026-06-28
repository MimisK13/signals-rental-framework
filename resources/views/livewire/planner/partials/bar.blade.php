{{--
    A single overlap job card: body + status row share one inset block aligned to day columns.
--}}
@php
    $colour = $row['colour'];
    $barLeft = (float) $row['left'];
    $barWidth = (float) $row['width'];
    $gapLeft = (int) ($row['gap_left_px'] ?? 0);
    $gapRight = (int) ($row['gap_right_px'] ?? 0);
    $statusRowHeight = $statusRowHeight ?? 14;
    $phaseColours = $phaseColours ?? [];
    $customerBadgeColours = $customerBadgeColours ?? [];
    $lineHeight = 12;
    $textSize = '11px';
    $tooltip = ($row['number'] ?? ('#'.$row['id']))
        .' · '.$row['state_label'].' · '.$row['status_label']
        .' · '.($row['starts_at']?->format('j M') ?? '')
        .' – '.($row['ends_at']?->format('j M') ?? '');
    $showUrl = route('opportunities.show', $row['id']);
    $contentHeight = 'calc(100% - '.$statusRowHeight.'px)';
@endphp

<div class="absolute left-0 right-0" style="top: {{ $top }}px; height: {{ $height }}px;" wire:key="job-{{ $row['id'] }}">
    <a href="{{ $showUrl }}" wire:navigate
       class="absolute block overflow-hidden rounded-none border-y border-black/10 shadow-sm no-underline text-white hover:brightness-105 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-white"
       style="left: calc({{ $barLeft }}% + {{ $gapLeft }}px); width: calc({{ $barWidth }}% - {{ $gapLeft + $gapRight }}px); top: 0; height: 100%; background: {{ $colour }};"
       title="{{ $tooltip }}"
       aria-label="{{ ($row['number'] ?? ('#'.$row['id'])).($row['subject'] ? ' — '.$row['subject'] : '') }}">

        @if($row['extends_before'])
            <div class="absolute left-0 top-0 bottom-0 z-20 w-1 bg-[var(--violet,#8b5cf6)]" title="{{ __('Extends beyond view') }}"></div>
        @endif
        @if($row['extends_after'])
            <div class="absolute right-0 top-0 bottom-0 z-20 w-1 bg-[var(--violet,#8b5cf6)]" title="{{ __('Extends beyond view') }}"></div>
        @endif

        <div class="relative z-10 flex flex-col gap-0 px-1 pt-1 pb-0.5" style="height: {{ $contentHeight }};">
            {{-- Line 1: number — subject --}}
            <div class="flex items-center gap-0.5 leading-none" style="height: {{ $lineHeight }}px; font-size: {{ $textSize }};">
                <flux:icon.document-text class="!size-3 shrink-0 opacity-70" />
                <span class="truncate font-semibold leading-none">
                    {{ $row['number'] ?? ('#'.$row['id']) }}
                    @if($row['subject'])
                        <span class="font-medium opacity-95"> — {{ $row['subject'] }}</span>
                    @endif
                </span>
            </div>

            {{-- Line 2: datetime range --}}
            <div class="flex items-center gap-0.5 truncate leading-none opacity-95" style="height: {{ $lineHeight }}px; font-size: {{ $textSize }};">
                <flux:icon.clock class="!size-3 shrink-0 opacity-70" />
                <span class="truncate">{{ $row['datetime_range'] }}</span>
            </div>

            {{-- Line 3: address --}}
            <div class="flex items-center gap-0.5 truncate leading-none opacity-90" style="height: {{ $lineHeight }}px; font-size: {{ $textSize }};">
                <flux:icon.map-pin class="!size-3 shrink-0 opacity-70" />
                <span class="truncate">{{ $row['address'] }}</span>
            </div>

            {{-- Line 4: status + Coll/Ret badges --}}
            <div class="flex items-center justify-between gap-1 leading-none opacity-95" style="height: {{ $lineHeight }}px; font-size: {{ $textSize }};">
                <span class="flex min-w-0 items-center gap-0.5 truncate leading-none">
                    <flux:icon.tag class="!size-3 shrink-0 opacity-70" />
                    <span class="truncate">{{ $row['status_label'] }}</span>
                </span>
                @if($row['customer_collecting'] || $row['customer_returning'])
                    <span class="flex shrink-0 items-center gap-0.5">
                        @if($row['customer_collecting'])
                            <span class="inline-flex items-center gap-px rounded-sm px-px py-px text-[7px] font-medium leading-none text-white"
                                  style="background: {{ $customerBadgeColours['collecting'] ?? '#27ae60' }};"
                                  title="{{ __('Customer collecting') }}">
                                <flux:icon.truck class="!size-1.5 shrink-0" />
                                {{ __('Coll') }}
                            </span>
                        @endif
                        @if($row['customer_returning'])
                            <span class="inline-flex items-center gap-px rounded-sm px-px py-px text-[7px] font-medium leading-none text-white"
                                  style="background: {{ $customerBadgeColours['returning'] ?? '#17a2b8' }};"
                                  title="{{ __('Customer returning') }}">
                                <flux:icon.truck class="!size-1.5 shrink-0" />
                                {{ __('Ret') }}
                            </span>
                        @endif
                    </span>
                @endif
            </div>
        </div>

        {{-- Status row: day columns relative to this job block (same width as body) --}}
        <div class="absolute bottom-0 left-0 right-0 z-10" style="height: {{ $statusRowHeight }}px;">
            @foreach($row['day_segments'] as $segment)
                @php
                    $segLeft = $barWidth > 0
                        ? (($segment['left'] - $barLeft) / $barWidth) * 100
                        : 0;
                    $segWidth = $barWidth > 0
                        ? ($segment['width'] / $barWidth) * 100
                        : 0;
                    $isLastSegment = $loop->last;
                    $phase = $segment['phase'];
                    $phaseColour = $phaseColours[$phase] ?? 'rgba(255,255,255,0.35)';
                    $segmentLabel = match ($phase) {
                        'delivery' => __('Del'),
                        'collection' => __('Coll'),
                        default => __('Use'),
                    };
                @endphp
                <div wire:key="seg-{{ $row['id'] }}-{{ $segment['day_index'] }}"
                     class="absolute top-0 box-border flex items-center justify-center overflow-hidden text-[4px] font-semibold uppercase tracking-tight text-white {{ $isLastSegment ? '' : 'border-r border-white/15' }}"
                     style="left: {{ $segLeft }}%; {{ $isLastSegment ? 'right: 0;' : 'width: '.$segWidth.'%;' }} height: {{ $statusRowHeight }}px; background-color: {{ $phaseColour }};"
                     title="{{ $segment['label'] }}">
                    @if($phase !== 'in-use')
                        <span class="max-w-full truncate leading-none">{{ $segmentLabel }}</span>
                    @endif
                </div>
            @endforeach
        </div>
    </a>
</div>
