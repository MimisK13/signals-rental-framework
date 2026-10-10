@props([
    'title' => null,
    'count' => null,
    'countClass' => 's-badge-zinc',
    'collapsible' => false,
    'open' => true,
])

@php
    $cardAttributes = $attributes->merge(['class' => 's-card s-table-card']);

    if ($collapsible) {
        $cardAttributes = $cardAttributes->merge([
            'x-data' => '{ open: '.($open ? 'true' : 'false').' }',
        ]);
    }
@endphp

<div {{ $cardAttributes }}>
    @if($title || $count !== null || isset($headerActions) || $collapsible)
        <div class="s-card-header">
            <div class="flex items-center gap-2">
                @if($title)
                    <span class="s-card-title">{{ $title }}</span>
                @endif

                @if($count !== null)
                    <span class="s-badge {{ $countClass }}">{{ $count }}</span>
                @endif
            </div>

            {{ $headerActions ?? '' }}

            @if($collapsible)
                <button
                    type="button"
                    class="s-btn-ghost s-btn-xs s-btn-icon ml-auto"
                    x-on:click="open = ! open"
                    :aria-expanded="open"
                    aria-label="{{ __('Toggle card') }}"
                    title="{{ __('Toggle card') }}"
                >
                    <svg class="h-4 w-4 transition-transform" :class="{ 'rotate-180': ! open }" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.51a.75.75 0 0 1-1.08 0l-4.25-4.51a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                    </svg>
                </button>
            @endif
        </div>
    @endif

    <div
        class="s-table-card-content"
        @if($collapsible) x-cloak x-show="open" @endif
    >
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="s-card-footer">{{ $footer }}</div>
    @endisset
</div>
