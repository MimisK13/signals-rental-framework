<section class="w-full">
    <x-dense::page-header :title="$repair->product?->name ?? 'Repair'">
        <x-slot:breadcrumbs><a href="{{ route('repairs.index') }}" wire:navigate.hover class="text-[var(--link)] hover:underline">Repairs</a></x-slot:breadcrumbs>
        <x-slot:meta><span class="text-sm text-[var(--text-muted)]">{{ $repair->status->label() }}</span></x-slot:meta>
        <x-slot:actions>
            @can('update', $repair)
                <a href="{{ route('repairs.edit', $repair) }}" wire:navigate.hover class="s-btn s-btn-sm s-btn-ghost">Edit</a>
                @if($repair->status->value === 'open') <flux:button wire:click="start" size="sm">Start repair</flux:button> @endif
                @if($repair->status->value !== 'completed') <flux:button wire:click="complete" size="sm" variant="primary">Complete</flux:button> @endif
                @if($repair->status->value !== 'completed' && $repair->status->value !== 'unrepairable') <flux:button wire:click="markUnrepairable" size="sm">Mark unrepairable</flux:button> @endif
            @endcan
        </x-slot:actions>
    </x-dense::page-header>

    <div class="grid gap-6 px-6 py-4 lg:grid-cols-2 max-md:px-5 max-sm:px-3">
        <x-signals.panel title="Repair">
            <x-signals.data-list layout="vertical" :items="[
                ['label' => 'Product', 'value' => $repair->product?->name ?? '—'],
                ['label' => 'Asset', 'value' => $repair->asset?->currentStockLevel?->asset_number ?? ($repair->asset_id ? 'Asset #'.$repair->asset_id : 'Bulk quantity')],
                ['label' => 'Store', 'value' => $repair->store?->name ?? '—'],
                ['label' => 'Quantity', 'value' => $repair->quantity],
                ['label' => 'Usable', 'value' => $repair->is_usable ? 'Yes' : 'No'],
                ['label' => 'Reported', 'value' => $formatter->dateTime($repair->reported_at)],
                ['label' => 'Started', 'value' => $repair->started_at ? $formatter->dateTime($repair->started_at) : '—'],
                ['label' => 'Expected completion', 'value' => $repair->expected_completed_at ? $formatter->dateTime($repair->expected_completed_at) : '—'],
                ['label' => 'Completed', 'value' => $repair->completed_at ? $formatter->dateTime($repair->completed_at) : '—'],
            ]" />
        </x-signals.panel>
        <x-signals.panel title="People and cost">
            <x-signals.data-list layout="vertical" :items="[
                ['label' => 'Reported by', 'value' => $repair->reportedBy?->name ?? '—'],
                ['label' => 'Assigned to', 'value' => $repair->assignedTo?->name ?? '—'],
                ['label' => 'External repairer', 'value' => $repair->externalRepairer?->name ?? '—'],
                ['label' => 'Repair expense', 'value' => $repair->expense ? $formatter->money($repair->expense->base_amount, $repair->expense->base_currency_code) : '—'],
                ['label' => 'Description', 'value' => $repair->description ?? '—'],
            ]" />
            @can('update', $repair)
                <div class="mt-4 flex gap-2">
                    @if(!$repair->is_usable)<flux:button wire:click="setUsability(true)" size="sm">Mark usable</flux:button>@else<flux:button wire:click="setUsability(false)" size="sm" variant="danger">Mark unusable</flux:button>@endif
                </div>
            @endcan
        </x-signals.panel>
    </div>
</section>
