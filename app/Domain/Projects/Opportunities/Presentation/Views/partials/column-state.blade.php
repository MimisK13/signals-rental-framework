@php
    $stateBadgeClass = match($item->state) {
        \App\Domain\Projects\Opportunities\Enums\OpportunityState::Draft => 's-badge-zinc',
        \App\Domain\Projects\Opportunities\Enums\OpportunityState::Quotation => 's-badge-blue',
        \App\Domain\Projects\Opportunities\Enums\OpportunityState::Order => 's-badge-green',
        default => 's-badge-zinc',
    };
@endphp
<span class="s-badge {{ $stateBadgeClass }}">{{ $item->state->label() }}</span>
