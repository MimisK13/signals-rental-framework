{{--
    Planner overlap grid: day headers, heatmap counts, lane-packed job cards.
--}}
@php
    /** @var list<array<string, mixed>> $rows */
    $rows = $this->rows;
    /** @var list<\Illuminate\Support\Carbon> $days */
    $days = $this->dayColumns();
    $dayCount = max(1, count($days));
    $colWidth = 100 / $dayCount;
    $jobCounts = $this->jobCountsPerDay;
    $heatmapMax = $this->jobCountHeatmapMax;
    $phaseColours = $this->phaseColours();
    $customerBadgeColours = $this->customerBadgeColours();
    $statusRowHeight = 18;
    $laneGap = $this->cardGapPx();
    $laneHeight = 58 + $statusRowHeight;
    $laneStride = $laneHeight + $laneGap;
    $today = \Illuminate\Support\Carbon::today('UTC')->toDateString();
    $gridStyle = "grid-template-columns: repeat({$dayCount}, minmax(0, 1fr))";
@endphp

@if(count($rows) === 0)
    <x-signals.empty
        title="{{ __('No jobs in this window') }}"
        description="{{ __('No quotes or orders fall within the selected dates and filters.') }}">
        <x-slot:icon><flux:icon.calendar-days class="!size-7" /></x-slot:icon>
    </x-signals.empty>
@else
    <div class="s-panel w-full" wire:loading.class="opacity-60">
        <div class="s-panel-body !p-0 w-full">
            <div class="w-full border-l border-[var(--card-border)]">
                {{-- Day header row --}}
                <div class="grid w-full border-b border-[var(--card-border)]" style="{{ $gridStyle }}">
                    @foreach($days as $day)
                        @php
                            $dateKey = $day->toDateString();
                            $isToday = $dateKey === $today;
                            $isWeekend = $day->isWeekend();
                        @endphp
                        <div class="border-r border-[var(--card-border)] py-1 text-center {{ $isWeekend ? 'bg-black/[0.04] dark:bg-white/[0.04]' : '' }} {{ $isToday ? 'bg-[#fff3cd]/50 ring-1 ring-inset ring-[#ffc107]/70' : '' }}"
                             wire:key="day-{{ $dateKey }}">
                            <div class="text-[11px] font-semibold text-[var(--text-primary)]">{{ $day->format('D') }}</div>
                            <div class="text-[10px] text-[var(--text-muted)]">{{ $day->format('j') }}</div>
                        </div>
                    @endforeach
                </div>

                {{-- Job-count heatmap row --}}
                <div class="grid w-full border-b border-[var(--card-border)]" style="{{ $gridStyle }}">
                    @foreach($days as $day)
                        @php
                            $dateKey = $day->toDateString();
                            $count = $jobCounts[$dateKey] ?? 0;
                            $isToday = $dateKey === $today;
                            $isWeekend = $day->isWeekend();
                            $intensity = min(1, $count / $heatmapMax);
                            $heatAlpha = 0.12 + ($intensity * 0.78);
                        @endphp
                        <div class="flex items-center justify-center border-r border-[var(--card-border)] py-1.5 text-[12px] font-bold text-[var(--text-primary)] {{ $isWeekend ? 'bg-black/[0.04] dark:bg-white/[0.04]' : '' }} {{ $isToday ? 'ring-1 ring-inset ring-[#ffc107]/50' : '' }}"
                             style="background-color: rgba(192, 57, 43, {{ $count > 0 ? $heatAlpha : 0 }});"
                             wire:key="heatmap-{{ $dateKey }}">
                            {{ $count }}
                        </div>
                    @endforeach
                </div>

                @php
                    $maxLanes = 1;
                    foreach ($rows as $r) {
                        $maxLanes = max($maxLanes, (int) ($r['lanes'] ?? 1));
                    }
                    $trackHeight = $maxLanes > 0
                        ? ($maxLanes * $laneStride - $laneGap)
                        : 0;
                @endphp
                <div class="relative w-full" style="height: {{ $trackHeight }}px;">
                    @foreach($days as $i => $day)
                        @php
                            $dateKey = $day->toDateString();
                            $isToday = $dateKey === $today;
                            $isWeekend = $day->isWeekend();
                        @endphp
                        <div class="absolute top-0 bottom-0 border-r border-[var(--card-border)] {{ $isWeekend ? 'bg-black/[0.04] dark:bg-white/[0.04]' : '' }} {{ $isToday ? 'bg-[#fff3cd]/25 ring-1 ring-inset ring-[#ffc107]/40' : '' }}"
                             style="left: {{ $i * $colWidth }}%; width: {{ $colWidth }}%;"
                             wire:key="grid-{{ $dateKey }}"></div>
                    @endforeach

                    @foreach($rows as $row)
                        @php
                            $lane = (int) ($row['lane'] ?? 0);
                            $top = $lane * $laneStride;
                        @endphp
                        @include('livewire.planner.partials.bar', [
                            'row' => $row,
                            'top' => $top,
                            'height' => $laneHeight,
                            'colWidth' => $colWidth,
                            'statusRowHeight' => $statusRowHeight,
                            'phaseColours' => $phaseColours,
                            'customerBadgeColours' => $customerBadgeColours,
                        ])
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endif
