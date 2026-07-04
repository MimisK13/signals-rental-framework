<?php

use App\Actions\PurchaseOrders\CancelPurchaseOrder;
use App\Actions\PurchaseOrders\DeleteDraftPurchaseOrder;
use App\Actions\PurchaseOrders\ReceivePurchaseOrder;
use App\Actions\PurchaseOrders\SendPurchaseOrder;
use App\Actions\VirtualStock\ReturnVirtualStockIntake;
use App\Data\PurchaseOrders\ReceivePurchaseOrderData;
use App\Data\VirtualStock\ReturnVirtualStockIntakeData;
use App\Livewire\Concerns\HandlesVerbsStateErrors;
use App\Models\PurchaseOrder;
use App\Models\VirtualStockIntake;
use App\Services\PurchaseOrders\PurchaseOrderStatusRegistry;
use App\Services\VirtualStock\VirtualStockIntakeReturnGuard;
use App\Services\VirtualStock\VirtualStockStatusRegistry;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    use HandlesVerbsStateErrors;

    public PurchaseOrder $purchaseOrder;

    public bool $showCancelModal = false;

    public bool $showReceiveModal = false;

    public bool $showReturnIntakeModal = false;

    public ?int $returnIntakeId = null;

    /** @var array<int, array{purchase_order_item_id: int, quantity: string}> */
    public array $receiveLines = [];

    public ?string $cancelReason = null;

    public function mount(PurchaseOrder $purchaseOrder): void
    {
        Gate::authorize('view', $purchaseOrder);

        $this->purchaseOrder = $purchaseOrder->load(['supplier', 'store', 'opportunity', 'items.product', 'virtualStockIntakes.product']);
        $this->resetReceiveLines();
    }

    public function rendering(View $view): void
    {
        $view->title($this->purchaseOrder->number);
    }

    public function send(): void
    {
        $this->runVerbsTransition(
            fn () => (new SendPurchaseOrder)($this->purchaseOrder),
            fn () => $this->refreshPurchaseOrder(),
        );
    }

    public function cancel(): void
    {
        $this->runVerbsTransition(function (): void {
            (new CancelPurchaseOrder)($this->purchaseOrder, $this->cancelReason);
            $this->showCancelModal = false;
            $this->refreshPurchaseOrder();
        });
    }

    public function receive(): void
    {
        $lines = ReceivePurchaseOrderData::validateAndCreate([
            'lines' => array_values($this->receiveLines),
        ]);

        $this->runVerbsTransition(function () use ($lines): void {
            app(ReceivePurchaseOrder::class)($this->purchaseOrder, $lines);

            $this->showReceiveModal = false;
            $this->refreshPurchaseOrder();
            $this->resetReceiveLines();
        });
    }

    public function openReceiveModal(): void
    {
        $this->resetReceiveLines();
        $this->showReceiveModal = true;
    }

    private function resetReceiveLines(): void
    {
        $this->receiveLines = [];

        foreach ($this->purchaseOrder->items as $item) {
            $remaining = bcsub(
                (string) $item->quantity,
                bcadd((string) $item->received_quantity, '0', 2),
                2,
            );

            if (bccomp($remaining, '0', 2) <= 0) {
                continue;
            }

            $this->receiveLines[$item->id] = [
                'purchase_order_item_id' => $item->id,
                'quantity' => $remaining,
            ];
        }
    }

    public function deleteDraft(): void
    {
        $this->runVerbsTransition(
            fn () => (new DeleteDraftPurchaseOrder)($this->purchaseOrder),
            fn () => $this->redirect(route('purchase-orders.index'), navigate: true),
        );
    }

    public function openReturnIntakeModal(int $intakeId): void
    {
        $this->returnIntakeId = $intakeId;
        $this->showReturnIntakeModal = true;
    }

    public function returnIntakeToSupplier(bool $force = false): void
    {
        if ($this->returnIntakeId === null) {
            return;
        }

        $intake = VirtualStockIntake::query()->findOrFail($this->returnIntakeId);

        app(ReturnVirtualStockIntake::class)(
            $intake,
            new ReturnVirtualStockIntakeData(force: $force),
        );

        $this->showReturnIntakeModal = false;
        $this->returnIntakeId = null;
        $this->refreshPurchaseOrder();
    }

    public function confirmReturnIntakeToSupplier(): void
    {
        $this->returnIntakeToSupplier(force: true);
    }

    private function refreshPurchaseOrder(): void
    {
        $this->purchaseOrder->refresh()->load(['supplier', 'store', 'opportunity', 'items.product', 'virtualStockIntakes.product']);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $registry = app(PurchaseOrderStatusRegistry::class);
        $virtualStockStatuses = app(VirtualStockStatusRegistry::class)->statuses();
        $returnIntake = $this->returnIntakeId !== null
            ? VirtualStockIntake::query()->find($this->returnIntakeId)
            : null;

        return [
            'statuses' => $registry->statuses(),
            'virtualStockStatuses' => $virtualStockStatuses,
            'canSend' => Gate::check('send', $this->purchaseOrder),
            'canReceive' => Gate::check('receive', $this->purchaseOrder),
            'canCancel' => Gate::check('cancel', $this->purchaseOrder)
                && $registry->canTransitionTo($this->purchaseOrder->status, 'cancelled'),
            'canUpdate' => Gate::check('update', $this->purchaseOrder),
            'canDelete' => Gate::check('delete', $this->purchaseOrder),
            'outstandingAllocations' => $returnIntake !== null
                ? app(VirtualStockIntakeReturnGuard::class)->outstandingAllocations($returnIntake)
                : [],
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
            @if($canReceive)
                <button type="button" wire:click="openReceiveModal" class="s-btn s-btn-sm s-btn-accent">Receive</button>
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
                                <th class="text-right">Received</th>
                                @costs
                                    <th class="text-right">Unit cost</th>
                                    <th class="text-right">Tax</th>
                                    <th class="text-right">Total</th>
                                @endcosts
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
                                    <td class="text-right" style="font-family: var(--font-mono);">{{ $item->received_quantity }}</td>
                                    @costs
                                        <td class="text-right" style="font-family: var(--font-mono);">{{ $formatter->money($item->unit_cost, $purchaseOrder->currency_code) }}</td>
                                        <td class="text-right" style="font-family: var(--font-mono);">{{ $formatter->money($item->tax, $purchaseOrder->currency_code) }}</td>
                                        <td class="text-right" style="font-family: var(--font-mono);">{{ $formatter->money($item->total, $purchaseOrder->currency_code) }}</td>
                                    @endcosts
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

        @if($purchaseOrder->po_type === 'sub_hire' && $purchaseOrder->virtualStockIntakes->isNotEmpty())
            <x-signals.card title="Virtual stock intakes">
                <x-signals.table-wrap>
                    <table class="s-table">
                        <thead>
                            <tr>
                                <th>Intake</th>
                                <th>Product</th>
                                <th>Status</th>
                                <th>Window</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($purchaseOrder->virtualStockIntakes as $intake)
                                @php
                                    $intakeStatus = $virtualStockStatuses[$intake->status] ?? [];
                                @endphp
                                <tr wire:key="po-intake-{{ $intake->id }}">
                                    <td>
                                        <a href="{{ route('virtual-stock.show', $intake) }}" wire:navigate class="text-[var(--link)] hover:underline">{{ $intake->number }}</a>
                                        @if($intake->isLateReturn())
                                            <span class="s-badge s-badge-amber ml-1">Late</span>
                                        @endif
                                    </td>
                                    <td>{{ $intake->product?->name ?? '—' }}</td>
                                    <td><span class="s-badge {{ $intakeStatus['badge_class'] ?? 's-badge-zinc' }}">{{ $intakeStatus['label'] ?? $intake->status }}</span></td>
                                    <td class="text-sm">@localdate($intake->available_from) – @localdate($intake->available_until)</td>
                                    <td class="text-right">
                                        @can('return', $intake)
                                            <button type="button" wire:click="openReturnIntakeModal({{ $intake->id }})" class="text-sm text-[var(--link)] hover:underline">Return to supplier</button>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-signals.table-wrap>
            </x-signals.card>
        @endif

        @costs
            <div class="flex justify-end">
                <dl class="w-full max-w-sm space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-[var(--text-muted)]">Subtotal</dt><dd style="font-family: var(--font-mono);">{{ $formatter->money($purchaseOrder->subtotal, $purchaseOrder->currency_code) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-[var(--text-muted)]">Tax</dt><dd style="font-family: var(--font-mono);">{{ $formatter->money($purchaseOrder->tax_total, $purchaseOrder->currency_code) }}</dd></div>
                    <div class="flex justify-between font-semibold border-t border-[var(--border)] pt-2"><dt>Total</dt><dd style="font-family: var(--font-mono);">{{ $formatter->money($purchaseOrder->total, $purchaseOrder->currency_code) }}</dd></div>
                </dl>
            </div>
        @endcosts

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

    @if($showReceiveModal)
        <flux:modal wire:model="showReceiveModal" class="max-w-lg">
            <flux:heading size="lg">Receive goods</flux:heading>
            <p class="mt-2 text-sm text-[var(--text-muted)]">Enter the quantity received for each line.</p>
            <div class="mt-4 space-y-3">
                @foreach($purchaseOrder->items as $item)
                    @php
                        $remaining = bcsub(
                            (string) $item->quantity,
                            bcadd((string) $item->received_quantity, '0', 2),
                            2,
                        );
                    @endphp
                    @if(bccomp($remaining, '0', 2) > 0)
                        <flux:field wire:key="receive-line-{{ $item->id }}">
                            <flux:label>{{ $item->description }} (max {{ $remaining }})</flux:label>
                            <flux:input
                                type="number"
                                step="0.01"
                                min="0.01"
                                max="{{ $remaining }}"
                                wire:model="receiveLines.{{ $item->id }}.quantity"
                            />
                            <input type="hidden" wire:model="receiveLines.{{ $item->id }}.purchase_order_item_id" value="{{ $item->id }}" />
                        </flux:field>
                    @endif
                @endforeach
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showReceiveModal', false)">Close</flux:button>
                <flux:button variant="primary" wire:click="receive">Record receipt</flux:button>
            </div>
        </flux:modal>
    @endif

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

    @if($showReturnIntakeModal)
        <flux:modal wire:model="showReturnIntakeModal" class="max-w-lg">
            <flux:heading size="lg">Return intake to supplier?</flux:heading>
            @if($outstandingAllocations !== [])
                <p class="mt-2 text-sm text-[var(--text-muted)]">
                    Linked opportunities still have quantity out on hire. Confirm to return anyway — opportunity allocations will not be changed.
                </p>
                <ul class="mt-4 space-y-2 text-sm">
                    @foreach($outstandingAllocations as $allocation)
                        <li wire:key="po-outstanding-{{ $allocation['opportunity_item_id'] }}">
                            <span class="font-medium">{{ $allocation['opportunity_subject'] }}</span>
                            — {{ $allocation['line_name'] }}
                            <span style="font-family: var(--font-mono);">({{ $allocation['quantity_out'] }} out)</span>
                        </li>
                    @endforeach
                </ul>
                <div class="mt-4 flex justify-end gap-2">
                    <flux:button variant="ghost" wire:click="$set('showReturnIntakeModal', false)">Cancel</flux:button>
                    <flux:button variant="danger" wire:click="confirmReturnIntakeToSupplier">Return anyway</flux:button>
                </div>
            @else
                <p class="mt-2 text-sm text-[var(--text-muted)]">This will void the intake supply window and remove apportioned costs.</p>
                <div class="mt-4 flex justify-end gap-2">
                    <flux:button variant="ghost" wire:click="$set('showReturnIntakeModal', false)">Cancel</flux:button>
                    <flux:button variant="primary" wire:click="returnIntakeToSupplier">Return to supplier</flux:button>
                </div>
            @endif
        </flux:modal>
    @endif
</section>
