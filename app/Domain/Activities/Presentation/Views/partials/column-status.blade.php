@php $statusEnum = $item->status_id; @endphp
<span
    class="s-badge {{ $statusEnum === \App\Domain\Activities\Enums\ActivityStatus::Completed ? 's-badge-green' : ($statusEnum === \App\Domain\Activities\Enums\ActivityStatus::Cancelled ? 's-badge-zinc' : 's-badge-amber') }}"
>
    {{ $statusEnum->label() }}
</span>
