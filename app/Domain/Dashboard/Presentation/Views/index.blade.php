<x-layouts.app :title="'Dashboard'">
    <div class="w-full min-w-0">
    @if (config('signals.setup_complete'))
        <livewire:dashboard.getting-started-checklist />
    @endif

    {{-- Subnav --}}
    <nav class="app-subnav">
        <div class="flex h-full items-center gap-0">
            <span class="subnav-link active">Overview</span>
        </div>
        <span class="ml-auto font-mono text-[9px] uppercase tracking-[0.06em] text-[var(--text-muted)]">
            {{ now()->format('M Y') }}
        </span>
    </nav>

    {{-- Content --}}
    <div class="flex-1 p-8 max-md:p-5 max-sm:p-3">

        <h1 class="mb-1 font-display text-base font-bold uppercase tracking-[0.04em] text-[var(--text-primary)]">
            Dashboard
        </h1>
        <p class="mb-4 text-[13px] text-[var(--text-secondary)]">
            Overview of your rental operations
        </p>

        {{-- KPI Cards --}}
        <livewire:dashboard.kpi-cards />

        {{-- Opportunity pipeline widget --}}
        @can('opportunities.access')
            <livewire:dashboard.opportunity-pipeline />
        @endcan

        {{-- Recent Opportunities Table --}}
        @can('opportunities.access')
            <livewire:dashboard.recent-opportunities />
        @endcan

        {{-- Quick Actions --}}
        <h2 class="s-panel-title mb-3">Quick Actions</h2>
        <div class="quick-actions">
            @can('opportunities.create')
            <a class="quick-action" href="{{ route('opportunities.create') }}" wire:navigate.hover>
                <flux:icon.briefcase class="quick-action-icon" />
                New Opportunity
            </a>
            @endcan
            @can('members.create')
            <a class="quick-action" href="{{ route('members.create') }}" wire:navigate.hover>
                <flux:icon.user-plus class="quick-action-icon" />
                Add Member
            </a>
            @endcan
            @can('invoices.create')
            <a class="quick-action" href="{{ route('invoices.create') }}" wire:navigate.hover>
                <flux:icon.document-plus class="quick-action-icon" />
                Create Invoice
            </a>
            @endcan
            @can('products.create')
            <a class="quick-action" href="{{ route('products.create') }}" wire:navigate.hover>
                <flux:icon.cube class="quick-action-icon" />
                Add Product
            </a>
            @endcan
        </div>

    </div>
    </div>
</x-layouts.app>
