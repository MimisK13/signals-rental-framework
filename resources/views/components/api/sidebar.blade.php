@php
    use App\Support\ActiveRoute;
@endphp

@canany(['api.access', 'api.explorer.use', 'api.logs.view', 'api.usage.view', 'webhooks.manage'])
    <nav class="s-admin-sidebar">
        @can('api.access')
            <a href="{{ route('api.index') }}" wire:navigate.hover
               class="s-admin-nav-item s-admin-nav-overview {{ ActiveRoute::is('api.index') ? 'active' : '' }}">
                <flux:icon.squares-2x2 class="s-admin-nav-icon" />
                Overview
            </a>
        @endcan

        <div class="s-admin-sidebar-title">API Console</div>

        @can('api.explorer.use')
            <a href="{{ route('api.explorer') }}" wire:navigate.hover
               class="s-admin-nav-item {{ ActiveRoute::is('api.explorer') ? 'active' : '' }}">
                <flux:icon.command-line class="s-admin-nav-icon" />
                Explorer
            </a>
        @endcan

        @can('api.logs.view')
            <a href="{{ route('api.logs') }}" wire:navigate.hover
               class="s-admin-nav-item {{ ActiveRoute::is('api.logs') ? 'active' : '' }}">
                <flux:icon.clipboard-document-list class="s-admin-nav-icon" />
                Request Log
            </a>
        @endcan

        @can('api.usage.view')
            <a href="{{ route('api.usage') }}" wire:navigate.hover
               class="s-admin-nav-item {{ ActiveRoute::is('api.usage') ? 'active' : '' }}">
                <flux:icon.chart-bar class="s-admin-nav-icon" />
                Usage
            </a>
        @endcan

        @can('webhooks.manage')
            <a href="{{ route('api.webhooks') }}" wire:navigate.hover
               class="s-admin-nav-item {{ ActiveRoute::is('api.webhooks') ? 'active' : '' }}">
                <flux:icon.signal class="s-admin-nav-icon" />
                Webhooks
            </a>
        @endcan
    </nav>
@endcanany
