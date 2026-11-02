@php
    $statusClass = match($item->status) {
        'draft' => 's-badge-zinc',
        'issued' => 's-badge-blue',
        'applied' => 's-badge-green',
        default => 's-badge-zinc',
    };
@endphp
<span class="s-badge {{ $statusClass }}">{{ config('credit_notes.statuses.'.$item->status.'.label', $item->status) }}</span>
