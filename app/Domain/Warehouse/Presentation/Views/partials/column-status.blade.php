@php
    $status = $item->statusEnum();
    $badgeClass = match ($status) {
        \App\Enums\OpportunityStatus::OrderActive => 's-badge-amber',
        \App\Enums\OpportunityStatus::OrderDispatched => 's-badge-blue',
        \App\Enums\OpportunityStatus::OrderOnHire => 's-badge-violet',
        default => 's-badge-green',
    };
@endphp
<span class="s-badge {{ $badgeClass }}">{{ $status->label() }}</span>
