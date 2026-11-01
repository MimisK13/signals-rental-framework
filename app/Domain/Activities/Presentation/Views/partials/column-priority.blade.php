@php $priorityEnum = $item->priority; @endphp
<span
    class="s-badge {{ $priorityEnum === \App\Domain\Activities\Enums\ActivityPriority::High ? 's-badge-red' : ($priorityEnum === \App\Domain\Activities\Enums\ActivityPriority::Low ? 's-badge-zinc' : 's-badge-blue') }}"
>
    {{ $priorityEnum->label() }}
</span>
