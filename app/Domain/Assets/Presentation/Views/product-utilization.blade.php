<section class="w-full">
    @include('products::partials.product-header', ['product' => $product, 'subpage' => 'Utilization'])
    @include('products::partials.product-tabs', ['product' => $product, 'activeTab' => 'utilization'])

    <div class="space-y-6 px-6 py-4 max-md:px-5 max-sm:px-3">
        <div class="s-panel">
            <div class="s-panel-body grid gap-4 md:grid-cols-2 md:items-end">
                <flux:input wire:model.live.debounce.300ms="from" type="datetime-local" label="From" />
                <flux:input wire:model.live.debounce.300ms="until" type="datetime-local" label="Until" />
            </div>
            @if($this->periodError())
                <div class="px-4 pb-4 text-sm text-red-700 dark:text-red-300">{{ $this->periodError() }}</div>
            @endif
        </div>

        @if($this->result)
            @php($result = $this->result)
            @if($result->completeness->value !== 'complete')
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
                    Fleet utilization is {{ $result->completeness->label() }}. {{ $result->assetsWithIncompleteData }} Asset(s) have incomplete denominator data.
                </div>
            @endif
            <x-signals.panel title="Weighted Fleet Utilization">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div><div class="text-2xl font-bold">{{ $this->percentage($result->actualRentalUtilizationRatio) }}</div><div class="text-xs uppercase tracking-wide text-[var(--text-muted)]">Actual Rental Utilization</div></div>
                    <div><div class="text-2xl font-bold">{{ $result->assetCount }}</div><div class="text-xs uppercase tracking-wide text-[var(--text-muted)]">Qualifying Assets</div></div>
                    <div><div class="text-2xl font-bold">{{ $result->assetsWithIncompleteData }}</div><div class="text-xs uppercase tracking-wide text-[var(--text-muted)]">Incomplete Assets</div></div>
                    <div><div class="text-2xl font-bold">{{ $result->rentalParticipationCount }}</div><div class="text-xs uppercase tracking-wide text-[var(--text-muted)]">Rental Participations</div></div>
                </div>
                <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div><div class="font-semibold">{{ $this->duration($result->totalPlannableDuration) }}</div><div class="text-xs uppercase tracking-wide text-[var(--text-muted)]">Plannable Asset-Time</div></div>
                    <div><div class="font-semibold">{{ $this->duration($result->totalRentalWithinPlannableDuration) }}</div><div class="text-xs uppercase tracking-wide text-[var(--text-muted)]">Rental Asset-Time</div></div>
                    <div><div class="font-semibold">{{ $this->duration($result->totalIdlePlannableDuration) }}</div><div class="text-xs uppercase tracking-wide text-[var(--text-muted)]">Idle Asset-Time</div></div>
                    <div><div class="font-semibold">{{ $this->duration($result->totalMaintenanceBlockedDuration) }}</div><div class="text-xs uppercase tracking-wide text-[var(--text-muted)]">Maintenance Blocked</div></div>
                </div>
                <div class="mt-4 text-sm text-[var(--text-muted)]">Lifecycle blocked: {{ $this->duration($result->totalLifecycleBlockedDuration) }}</div>
            </x-signals.panel>
        @endif
    </div>
</section>
