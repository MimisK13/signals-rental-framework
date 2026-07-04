<?php

use App\Actions\VirtualStock\CancelVirtualStockIntake;
use App\Actions\VirtualStock\ConfirmVirtualStockIntake;
use App\Actions\VirtualStock\DeleteDraftVirtualStockIntake;
use App\Actions\VirtualStock\ReceiveVirtualStockIntake;
use App\Actions\VirtualStock\ReturnVirtualStockIntake;
use App\Models\VirtualStockIntake;
use App\Services\VirtualStock\VirtualStockStatusRegistry;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public VirtualStockIntake $virtualStockIntake;

    public function mount(VirtualStockIntake $virtualStockIntake): void
    {
        Gate::authorize('view', $virtualStockIntake);
        $this->virtualStockIntake = $virtualStockIntake->load(['product', 'store', 'purchaseOrder', 'purchaseOrderItem']);
    }

    public function confirm(): void
    {
        (new ConfirmVirtualStockIntake)($this->virtualStockIntake);
        $this->virtualStockIntake->refresh()->load(['product', 'store']);
    }

    public function receive(): void
    {
        (new ReceiveVirtualStockIntake)($this->virtualStockIntake);
        $this->virtualStockIntake->refresh();
    }

    public function returnIntake(): void
    {
        (new ReturnVirtualStockIntake)($this->virtualStockIntake);
        $this->virtualStockIntake->refresh();
    }

    public function cancel(): void
    {
        (new CancelVirtualStockIntake)($this->virtualStockIntake);
        $this->virtualStockIntake->refresh();
    }

    public function deleteDraft(): void
    {
        (new DeleteDraftVirtualStockIntake)($this->virtualStockIntake);
        $this->redirect(route('virtual-stock.index'), navigate: true);
    }

    public function with(): array
    {
        $registry = app(VirtualStockStatusRegistry::class);

        return [
            'statuses' => $registry->statuses(),
            'canConfirm' => Gate::check('confirm', $this->virtualStockIntake),
            'canReceive' => Gate::check('receive', $this->virtualStockIntake),
            'canReturn' => Gate::check('return', $this->virtualStockIntake),
            'canCancel' => Gate::check('cancel', $this->virtualStockIntake),
            'canUpdate' => Gate::check('update', $this->virtualStockIntake),
            'canDelete' => Gate::check('delete', $this->virtualStockIntake),
        ];
    }
}; ?>

<section class="w-full">
    @php
        $formatter = app(\App\Support\Formatter::class);
        $statusMeta = $statuses[$virtualStockIntake->status] ?? [];
    @endphp

    <x-signals.page-header :title="$virtualStockIntake->number">
        <x-slot:breadcrumbs>
            <a href="{{ route('virtual-stock.index') }}" wire:navigate class="text-[var(--link)] hover:underline">Virtual Stock</a>
            <span class="mx-1 text-[var(--text-muted)]">/</span>
            <span>{{ $virtualStockIntake->number }}</span>
        </x-slot:breadcrumbs>
        <x-slot:meta>
            <span class="s-badge {{ $statusMeta['badge_class'] ?? 's-badge-zinc' }}">{{ $statusMeta['label'] ?? $virtualStockIntake->status }}</span>
        </x-slot:meta>
        <x-slot:actions>
            @if($canConfirm)
                <button type="button" wire:click="confirm" class="s-btn s-btn-sm s-btn-accent">Confirm</button>
            @endif
            @if($canReceive)
                <button type="button" wire:click="receive" class="s-btn s-btn-sm s-btn-accent">Receive</button>
            @endif
            @if($canReturn)
                <button type="button" wire:click="returnIntake" class="s-btn s-btn-sm s-btn-secondary">Return</button>
            @endif
            @if($canCancel)
                <button type="button" wire:click="cancel" wire:confirm="Cancel this virtual stock intake?" class="s-btn s-btn-sm s-btn-secondary">Cancel</button>
            @endif
            @if($canDelete)
                <button type="button" wire:click="deleteDraft" wire:confirm="Delete this draft intake?" class="s-btn s-btn-sm s-btn-danger">Delete</button>
            @endif
        </x-slot:actions>
    </x-signals.page-header>

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3 space-y-6">
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <x-signals.panel title="Product"><p>{{ $virtualStockIntake->product?->name ?? '—' }}</p></x-signals.panel>
            <x-signals.panel title="Store"><p>{{ $virtualStockIntake->store?->name ?? '—' }}</p></x-signals.panel>
            <x-signals.panel title="Quantity"><p style="font-family: var(--font-mono);">{{ $virtualStockIntake->quantity }}</p></x-signals.panel>
            <x-signals.panel title="Unit cost"><p style="font-family: var(--font-mono);">{{ $formatter->money($virtualStockIntake->unit_cost, $virtualStockIntake->currency_code) }}</p></x-signals.panel>
        </div>
        <x-signals.panel title="Availability window">
            <p>@localdatetime($virtualStockIntake->available_from) → @localdatetime($virtualStockIntake->available_until)</p>
        </x-signals.panel>
    </div>
</section>
