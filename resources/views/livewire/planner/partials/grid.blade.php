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
    $laneHeight = 108;
    $today = \Illuminate\Support\Carbon::today('UTC')->toDateString();
@endphp

@if(count($rows) === 0)
    <x-signals.empty
        title="{{ __('No jobs in this window') }}"
        description="{{ __('No quotes or orders fall within the selected dates and filters.') }}">
        <x-slot:icon><flux:icon.calendar-days class="!size-7" /></x-slot:icon>
    </x-signals.empty>
@else
    <div class="s-panel overflow-x-auto" wire:loading.class="opacity-60">
        <div class="s-panel-body" style="min-width: 720px;">
            {{-- Day header row --}}
            <div class="relative mb-0 flex border-b border-[var(--card-border)]">
                @foreach($days as $day)
                    @php
                        $dateKey = $day->toDateString();
                        $isToday = $dateKey === $today;
                        $isWeekend = $day->isWeekend();
                    @endphp
                    <div class="shrink-0 py-1 text-center text-[10px] {{ $isWeekend ? 'bg-black/[0.04] dark:bg-white/[0.04]' : '' }} {{ $isToday ? 'ring-1 ring-inset ring-[#ffc107]/70 bg-[#fff3cd]/50' : '' }}"
                         style="width: {{ $colWidth }}%;"
                         wire:key="day-{{ $dateKey }}">
                        <div class="font-semibold text-[var(--text-primary)]">{{ $day->format('D') }}</div>
                        <div class="text-[var(--text-muted)]">{{ $day->format('j') }}</div>
                    </div>
                @endforeach
            </div>

            {{-- Job-count heatmap row --}}
            <div class="relative mb-2 flex border-b border-[var(--card-border)]">
                @foreach($days as $day)
                    @php
                        $dateKey = $day->toDateString();
                        $count = $jobCounts[$dateKey] ?? 0;
                        $isToday = $dateKey === $today;
                        $isWeekend = $day->isWeekend();
                        $intensity = min(1, $count / $heatmapMax);
                        $heatAlpha = 0.12 + ($intensity * 0.78);
                    @endphp
                    <div class="flex shrink-0 items-center justify-center py-1.5 text-[11px] font-bold text-[var(--text-primary)] {{ $isWeekend ? 'bg-black/[0.04] dark:bg-white/[0.04]' : '' }} {{ $isToday ? 'ring-1 ring-inset ring-[#ffc107]/50' : '' }}"
                         style="width: {{ $colWidth }}%; background-color: rgba(192, 57, 43, {{ $count > 0 ? $heatAlpha : 0 }});"
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
                $trackHeight = $maxLanes * $laneHeight + 8;
            @endphp
            <div class="relative" style="height: {{ $trackHeight }}px;">
                @foreach($days as $i => $day)
                    @php
                        $dateKey = $day->toDateString();
                        $isToday = $dateKey === $today;
                        $isWeekend = $day->isWeekend();
                    @endphp
                    <div class="absolute top-0 bottom-0 border-l border-[var(--card-border)]/60 {{ $isWeekend ? 'bg-black/[0.04] dark:bg-white/[0.04]' : '' }} {{ $isToday ? 'bg-[#fff3cd]/25 ring-1 ring-inset ring-[#ffc107]/40' : '' }}"
                         style="left: {{ $i * $colWidth }}%; width: {{ $colWidth }}%;"
                         wire:key="grid-{{ $dateKey }}"></div>
                @endforeach

                @foreach($rows as $row)
                    @php
                        $lane = (int) ($row['lane'] ?? 0);
                        $top = $lane * $laneHeight + 4;
                    @endphp
                    <div wire:key="job-{{ $row['id'] }}" class="absolute left-0 right-0">
                        @include('livewire.planner.partials.bar', [
                            'row' => $row,
                            'top' => $top,
                            'height' => $laneHeight - 8,
                            'colWidth' => $colWidth,
                        ])
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif
