{{--
    Planner day-column header + job-count heatmap (sticky with filter bar + legend).
--}}
@php
    /** @var list<\Illuminate\Support\Carbon> $days */
    $days = $this->dayColumns();
    $dayCount = max(1, count($days));
    $jobCounts = $this->jobCountsPerDay;
    $heatmapMax = $this->jobCountHeatmapMax;
    $today = \Illuminate\Support\Carbon::today('UTC')->toDateString();
    $gridStyle = "grid-template-columns: repeat({$dayCount}, minmax(0, 1fr))";
@endphp

<div class="overflow-hidden rounded-t-sm border border-b-0 border-[var(--card-border)] bg-[var(--card-bg)]">
    {{-- Day header row --}}
    <div class="grid w-full shrink-0 border-b border-[var(--card-border)]" style="{{ $gridStyle }}">
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
    <div class="grid w-full shrink-0 border-b border-[var(--card-border)]" style="{{ $gridStyle }}">
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
</div>
