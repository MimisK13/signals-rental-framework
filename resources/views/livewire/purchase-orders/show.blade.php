<?php

use App\Actions\PurchaseOrders\CancelPurchaseOrder;
use App\Actions\PurchaseOrders\DeleteDraftPurchaseOrder;
use App\Actions\PurchaseOrders\SendPurchaseOrder;
use App\Models\PurchaseOrder;
use App\Services\PurchaseOrders\PurchaseOrderStatusRegistry;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public PurchaseOrder $purchaseOrder;

    public bool $showCancelModal = false;

    public ?string $cancelReason = null;

    public function mount(PurchaseOrder $purchaseOrder): void
    {
        Gate::authorize('view', $purchaseOrder);

        $this->purchaseOrder = $purchaseOrder->load(['supplier', 'store', 'opportunity', 'items.product']);
    }

    public function rendering(View $view): void
    {
        $view->title($this->purchaseOrder->number);
    }

    public function send(): void
    {
        (new SendPurchaseOrder)($this->purchaseOrder);
        $this->refreshPurchaseOrder();
    }

    public function cancel(): void
    {
        (new CancelPurchaseOrder)($this->purchaseOrder, $this->cancelReason);
        $this->showCancelModal = false;
        $this->refreshPurchaseOrder();
    }

    public function deleteDraft(): void
    {
        (new DeleteDraftPurchaseOrder)($this->purchaseOrder);
        $this->redirect(route('purchase-orders.index'), navigate: true);
    }

    private function refreshPurchaseOrder(): void
    {
        $this->purchaseOrder->refresh()->load(['supplier', 'store', 'opportunity', 'items.product']);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $registry = app(PurchaseOrderStatusRegistry::class);

        return [
            'statuses' => $registry->statuses(),
            'canSend' => Gate::check('send', $this->purchaseOrder),
            'canCancel' => Gate::check('cancel', $this->purchaseOrder)
                && $registry->canTransitionTo($this->purchaseOrder->status, 'cancelled'),
            'canUpdate' => Gate::check('update', $this->purchaseOrder),
            'canDelete' => Gate::check('delete', $this->purchaseOrder),
        ];
    }
}; ?>

<section class="w-full">
    @php
        $formatter = app(\App\Support\Formatter::class);
        $statusMeta = $statuses[$purchaseOrder->status] ?? [];
        $statusClass = $statusMeta['badge_class'] ?? 's-badge-zinc';
    @endphp

    <x-signals.page-header :title="$purchaseOrder->number">
        <x-slot:breadcrumbs>
            <a href="{{ route('purchase-orders.index') }}" wire:navigate class="text-[var(--link)] hover:underline">Purchase Orders</a>
            <span class="mx-1 text-[var(--text-muted)]">/</span>
            <span>{{ $purchaseOrder->number }}</span>
        </x-slot:breadcrumbs>
        <x-slot:meta>
            <span class="s-badge {{ $statusClass }}">{{ $statusMeta['label'] ?? $purchaseOrder->status }}</span>
        </x-slot:meta>
        <x-slot:actions>
            @if($canUpdate)
                <a href="{{ route('purchase-orders.edit', $purchaseOrder) }}" wire:navigate class="s-btn s-btn-sm s-btn-secondary">Edit</a>
            @endif
            @if($canSend)
                <button type="button" wire:click="send" wire:confirm="Send this purchase order to the supplier?" class="s-btn s-btn-sm s-btn-accent">Send</button>
            @endif
            @if($canCancel)
                <button type="button" wire:click="$set('showCancelModal', true)" class="s-btn s-btn-sm s-btn-secondary">Cancel</button>
            @endif
            @if($canDelete)
                <button type="button" wire:click="deleteDraft" wire:confirm="Delete this draft purchase order?" class="s-btn s-btn-sm s-btn-danger">Delete</button>
            @endif
        </x-slot:actions>
    </x-signals.page-header>

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3 space-y-6">
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <x-signals.panel title="Supplier">
                <p class="font-medium">{{ $purchaseOrder->supplier?->name ?? '—' }}</p>
            </x-signals.panel>
            <x-signals.panel title="Store">
                <p>{{ $purchaseOrder->store?->name ?? '—' }}</p>
            </x-signals.panel>
            <x-signals.panel title="Type">
                <p>{{ config('purchase_orders.types.'.$purchaseOrder->po_type.'.label', $purchaseOrder->po_type) }}</p>
            </x-signals.panel>
            <x-signals.panel title="Reference">
                <p>{{ $purchaseOrder->reference ?: '—' }}</p>
            </x-signals.panel>
        </div>

        @if($purchaseOrder->notes)
            <x-signals.panel title="Notes">
                <p class="whitespace-pre-wrap text-sm">{{ $purchaseOrder->notes }}</p>
            </x-signals.panel>
        @endif

        <x-signals.card title="Line items">
            @if($purchaseOrder->items->isEmpty())
                <p class="text-sm text-[var(--text-muted)]">No line items.</p>
            @else
                <x-signals.table-wrap>
                    <table class="s-table">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th class="text-right">Qty</th>
                                <th class="text-right">Unit cost</th>
                                <th class="text-right">Tax</th>
                                <th class="text-right">Total</th>
                                @if($purchaseOrder->po_type === 'sub_hire')
                                    <th></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($purchaseOrder->items as $item)
                                <tr wire:key="po-item-{{ $item->id }}">
                                    <td>
                                        {{ $item->description }}
                                        @if($item->product)
                                            <span class="text-xs text-[var(--text-muted)] block">{{ $item->product->name }}</span>
                                        @endif
                                    </td>
                                    <td class="text-right" style="font-family: var(--font-mono);">{{ $item->quantity }}</td>
                                    <td class="text-right" style="font-family: var(--font-mono);">{{ $formatter->money($item->unit_cost, $purchaseOrder->currency_code) }}</td>
                                    <td class="text-right" style="font-family: var(--font-mono);">{{ $formatter->money($item->tax, $purchaseOrder->currency_code) }}</td>
                                    <td class="text-right" style="font-family: var(--font-mono);">{{ $formatter->money($item->total, $purchaseOrder->currency_code) }}</td>
                                    @if($purchaseOrder->po_type === 'sub_hire' && $item->product_id)
                                        <td class="text-right">
                                            @can('create', App\Models\VirtualStockIntake::class)
                                                <a href="{{ route('virtual-stock.create', ['purchase_order_id' => $purchaseOrder->id, 'purchase_order_item_id' => $item->id]) }}" wire:navigate class="text-sm text-[var(--link)] hover:underline">Create intake</a>
                                            @endcan
                                        </td>
                                    @elseif($purchaseOrder->po_type === 'sub_hire')
                                        <td></td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-signals.table-wrap>
            @endif
        </x-signals.card>

        <div class="flex justify-end">
            <dl class="w-full max-w-sm space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-[var(--text-muted)]">Subtotal</dt><dd style="font-family: var(--font-mono);">{{ $formatter->money($purchaseOrder->subtotal, $purchaseOrder->currency_code) }}</dd></div>
                <div class="flex justify-between"><dt class="text-[var(--text-muted)]">Tax</dt><dd style="font-family: var(--font-mono);">{{ $formatter->money($purchaseOrder->tax_total, $purchaseOrder->currency_code) }}</dd></div>
                <div class="flex justify-between font-semibold border-t border-[var(--border)] pt-2"><dt>Total</dt><dd style="font-family: var(--font-mono);">{{ $formatter->money($purchaseOrder->total, $purchaseOrder->currency_code) }}</dd></div>
            </dl>
        </div>

        <x-signals.panel title="Status timeline">
            <ul class="space-y-2 text-sm">
                <li>Draft — created @if($purchaseOrder->created_at) @localdatetime($purchaseOrder->created_at) @endif</li>
                @if($purchaseOrder->sent_at)
                    <li>Sent — @localdatetime($purchaseOrder->sent_at)</li>
                @endif
                @if($purchaseOrder->received_at)
                    <li>Received — @localdatetime($purchaseOrder->received_at)</li>
                @endif
                @if($purchaseOrder->cancelled_at)
                    <li>Cancelled — @localdatetime($purchaseOrder->cancelled_at)</li>
                @endif
            </ul>
        </x-signals.panel>
    </div>

    @if($showCancelModal)
        <flux:modal wire:model="showCancelModal" class="max-w-md">
            <flux:heading size="lg">Cancel purchase order</flux:heading>
            <flux:field class="mt-4">
                <flux:label>Reason (optional)</flux:label>
                <flux:textarea wire:model="cancelReason" rows="3" />
            </flux:field>
            <div class="mt-4 flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showCancelModal', false)">Close</flux:button>
                <flux:button variant="danger" wire:click="cancel">Cancel PO</flux:button>
            </div>
        </flux:modal>
    @endif
</section>
