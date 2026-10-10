<?php

use App\Actions\SupplierBills\IssueSupplierBill;
use App\Actions\SupplierBills\VoidSupplierBill;
use App\Models\SupplierBill;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public SupplierBill $supplierBill;

    public function mount(SupplierBill $supplierBill): void
    {
        Gate::authorize('view', $supplierBill);
        $this->supplierBill = $supplierBill->load(['supplier', 'purchaseOrder', 'items.product', 'items.purchaseOrderItem', 'items.virtualStockIntake']);
    }

    public function issue(): void
    {
        (new IssueSupplierBill)($this->supplierBill);
        $this->supplierBill->refresh()->load(['supplier', 'purchaseOrder', 'items.product', 'items.purchaseOrderItem', 'items.virtualStockIntake']);
    }

    public function void(): void
    {
        (new VoidSupplierBill)($this->supplierBill);
        $this->supplierBill->refresh();
    }

    public function with(): array
    {
        return [
            'canUpdate' => Gate::check('update', $this->supplierBill),
            'canIssue' => Gate::check('issue', $this->supplierBill),
            'canVoid' => Gate::check('void', $this->supplierBill),
        ];
    }
}; ?>

<section class="w-full">
    <x-signals.page-header :title="$supplierBill->document_number">
        <x-slot:breadcrumbs><a href="{{ route('supplier-bills.index') }}" wire:navigate.hover class="text-[var(--link)] hover:underline">Supplier Bills</a></x-slot:breadcrumbs>
        <x-slot:actions>
            @if($canUpdate)<a href="{{ route('supplier-bills.edit', $supplierBill) }}" wire:navigate.hover class="s-btn s-btn-secondary">Edit</a>@endif
            @if($canIssue)<button type="button" wire:click="issue" class="s-btn s-btn-primary">Issue</button>@endif
            @if($canVoid)<button type="button" wire:click="void" wire:confirm="Void this supplier bill?" class="s-btn s-btn-danger">Void</button>@endif
        </x-slot:actions>
    </x-signals.page-header>

    <div class="max-w-6xl px-6 py-4 max-md:px-5 max-sm:px-3 space-y-6">
        <div class="grid gap-4 md:grid-cols-4">
            <x-signals.card title="Supplier"><p>{{ $supplierBill->supplier->name }}</p><p class="text-sm text-[var(--text-muted)]">{{ $supplierBill->status->label() }}</p></x-signals.card>
            <x-signals.card title="Issued"><p>{{ $supplierBill->issued_at?->format('d M Y') ?? 'Draft' }}</p><p class="text-sm text-[var(--text-muted)]">{{ $supplierBill->currency_code }}</p></x-signals.card>
            <x-signals.card title="Purchase Order"><p>{{ $supplierBill->purchaseOrder?->number ?? '—' }}</p></x-signals.card>
            <x-signals.card title="Total"><p class="text-lg font-semibold">{{ $supplierBill->formatMoneyCost('total') }}</p><p class="text-sm text-[var(--text-muted)]">Net {{ $supplierBill->formatMoneyCost('subtotal') }}</p></x-signals.card>
        </div>
        <x-signals.card title="Bill lines">
            <x-signals.table-wrap><table class="s-table w-full"><thead><tr><th>Product</th><th>Description</th><th>Qty</th><th>Unit cost</th><th>Tax</th><th>Total</th><th>Source</th></tr></thead><tbody>
            @forelse($supplierBill->items as $item)
                <tr wire:key="bill-item-{{ $item->id }}"><td>{{ $item->product?->name ?? 'Unmapped' }}</td><td>{{ $item->description }}</td><td>{{ $item->quantity }}</td><td>{{ number_format($item->unit_cost / 100, 2) }} {{ $supplierBill->currency_code }}</td><td>{{ number_format($item->tax / 100, 2) }} {{ $supplierBill->currency_code }}</td><td>{{ number_format($item->total / 100, 2) }} {{ $supplierBill->currency_code }}</td><td>{{ $item->purchaseOrderItem?->purchaseOrder?->number ?? ($item->virtualStockIntake?->number ?? '—') }}</td></tr>
            @empty
                <tr><td colspan="7" class="py-8 text-center text-[var(--text-muted)]">No bill lines.</td></tr>
            @endforelse
            </tbody></table></x-signals.table-wrap>
        </x-signals.card>
    </div>
</section>
