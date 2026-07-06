@php
    $effective = $item->effectiveStatus();
    $statusClass = match($effective) {
        'draft' => 's-badge-zinc',
        'issued', 'partially_paid' => 's-badge-blue',
        'paid' => 's-badge-green',
        'overdue' => 's-badge-red',
        'void', 'credited' => 's-badge-amber',
        default => 's-badge-zinc',
    };
@endphp
<span class="s-badge {{ $statusClass }}">{{ config('invoices.statuses.'.$effective.'.label', $effective) }}</span>
