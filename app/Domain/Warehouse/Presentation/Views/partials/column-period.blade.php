@php
    $start = $item->planning_starts_at ?? $item->starts_at;
    $end = $item->planning_ends_at ?? $item->ends_at;
@endphp
<span class="whitespace-nowrap">
    @if ($start === null && $end === null)
        No period
    @elseif ($start === null)
        Until {{ $end->format('d M Y') }}
    @elseif ($end === null || $start->isSameDay($end))
        {{ $start->format('d M Y') }}
    @else
        {{ $start->format('d M').' – '.$end->format('d M Y') }}
    @endif
</span>
