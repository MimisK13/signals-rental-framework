<x-signals.column-toggle />
<x-signals.export-button />
@can('create', App\Models\VirtualStockIntake::class)
    <a href="{{ route('virtual-stock.create') }}" wire:navigate.hover class="s-btn s-btn-sm s-btn-accent">New Intake</a>
@endcan
