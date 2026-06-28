{{--
    Planner overlap grid: date axis with per-day counts plus lane-packed job bars.
--}}
@php
    /** @var list<array<string, mixed>> $rows */
    $rows = $this->rows;
    /** @var list<\Illuminate\Support\Carbon> $days */
    $days = $this->dayColumns();
    $dayCount = max(1, count($days));
    $colWidth = 100 / $dayCount;
    $jobCounts = $this->jobCountsPerDay;
    $laneHeight = 80;
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
            {{-- Date axis with per-day counts --}}
            <div class="relative mb-2 flex border-b border-[var(--card-border)] pb-1">
                @foreach($days as $day)
                    @php
                        $dateKey = $day->toDateString();
                        $count = $jobCounts[$dateKey] ?? 0;
                        $isToday = $dateKey === $today;
                    @endphp
                    <div class="shrink-0 text-center text-[10px] {{ $day->isWeekend() ? 'text-[var(--text-muted)]' : 'text-[var(--text-secondary,var(--text-muted))]' }} {{ $isToday ? 'rounded-sm bg-[#fff3cd] ring-1 ring-[#ffc107]/60' : '' }}"
                         style="width: {{ $colWidth }}%;"
                         wire:key="day-{{ $dateKey }}">
                        <div class="font-semibold">{{ $day->format('D') }} {{ $day->format('j') }}</div>
                        <div class="text-[9px] font-medium opacity-80">{{ $count }}</div>
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
                    @endphp
                    <div class="absolute top-0 bottom-0 border-l {{ $isToday ? 'border-[#ffc107]/50 bg-[#fff3cd]/30' : 'border-[var(--card-border)] opacity-40' }}"
                         style="left: {{ $i * $colWidth }}%; width: {{ $colWidth }}%;"
                         wire:key="grid-{{ $dateKey }}"></div>
                @endforeach

                @foreach($rows as $row)
                    @php
                        $lane = (int) ($row['lane'] ?? 0);
                        $top = $lane * $laneHeight + 4;
                    @endphp
                    <div wire:key="job-{{ $row['id'] }}" class="absolute left-0 right-0">
                        @include('livewire.planner.partials.bar', ['row' => $row, 'top' => $top, 'height' => $laneHeight - 8, 'withLabel' => true])
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif
