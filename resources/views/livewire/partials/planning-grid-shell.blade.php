{{--
    Deferred grid loading shell — initial skeleton via wire:init, then wire:loading on refresh.

    Requires `gridReady` (bool) and `initializeGrid()` on the host Volt component.
--}}
@props([
    'contentView',
    'skeletonView',
])

<div wire:init="initializeGrid" class="s-planning-grid-stage w-full min-w-0">
    @if(! $gridReady)
        @include($skeletonView)
    @else
        <div wire:loading.remove class="s-planning-grid-content w-full min-w-0">
            @include($contentView)
        </div>
        <div wire:loading.flex class="s-planning-grid-skeleton w-full min-w-0">
            @include($skeletonView)
        </div>
    @endif
</div>
