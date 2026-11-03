@php
    $status = $item->statusEnum();
    $badgeClass = match ($status) {
        \App\Domain\Projects\Opportunities\Enums\OpportunityStatus::OrderActive => 's-badge-amber',
        \App\Domain\Projects\Opportunities\Enums\OpportunityStatus::OrderDispatched => 's-badge-blue',
        \App\Domain\Projects\Opportunities\Enums\OpportunityStatus::OrderOnHire => 's-badge-violet',
        default => 's-badge-green',
    };
@endphp
<span class="s-badge {{ $badgeClass }}">{{ $status->label() }}</span>
