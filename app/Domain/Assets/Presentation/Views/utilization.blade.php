<section class="w-full">
    <x-dense::page-header :title="$asset->product?->name ?? 'Asset'" :subtitle="'Utilization'">
        <x-slot:meta>
            <span class="text-sm text-[var(--text-muted)]">{{ $asset->currentStockLevel?->asset_number ?? 'Serialized Asset' }}</span>
        </x-slot:meta>
    </x-dense::page-header>

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
                    <div class="font-semibold">Utilization {{ $result->completeness->label() }}</div>
                    <ul class="mt-1 list-disc space-y-1 pl-5">
                        @forelse($result->unavailableReasons as $reason)
                            <li>{{ $this->reason($reason) }}</li>
                        @empty
                            <li>The utilization denominator is unavailable.</li>
                        @endforelse
                    </ul>
                </div>
            @endif

            <x-signals.panel title="Actual Utilization">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div><div class="text-2xl font-bold">{{ $this->percentage($result->actualRentalUtilizationRatio) }}</div><div class="text-xs uppercase tracking-wide text-[var(--text-muted)]">Rental Utilization</div></div>
                    <div><div class="text-2xl font-bold">{{ $this->duration($result->plannableDuration) }}</div><div class="text-xs uppercase tracking-wide text-[var(--text-muted)]">Plannable Time</div></div>
                    <div><div class="text-2xl font-bold">{{ $this->duration($result->actualRentalDuration) }}</div><div class="text-xs uppercase tracking-wide text-[var(--text-muted)]">Actual Rental Time</div></div>
                    <div><div class="text-2xl font-bold">{{ $this->duration($result->idlePlannableDuration) }}</div><div class="text-xs uppercase tracking-wide text-[var(--text-muted)]">Idle Time</div></div>
                </div>
                <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div><div class="font-semibold">{{ $this->duration($result->maintenanceBlockedDuration) }}</div><div class="text-xs uppercase tracking-wide text-[var(--text-muted)]">Maintenance Blocked</div></div>
                    <div><div class="font-semibold">{{ $this->duration($result->lifecycleBlockedDuration) }}</div><div class="text-xs uppercase tracking-wide text-[var(--text-muted)]">Lifecycle Blocked</div></div>
                    <div><div class="font-semibold">{{ $this->duration($result->ownedDuration) }}</div><div class="text-xs uppercase tracking-wide text-[var(--text-muted)]">Owned Time</div></div>
                    <div><div class="font-semibold">{{ $result->rentalParticipationCount }}</div><div class="text-xs uppercase tracking-wide text-[var(--text-muted)]">Rental Participations</div></div>
                </div>
            </x-signals.panel>
        @endif
    </div>
</section>
