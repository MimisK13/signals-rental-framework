<?php

use App\Actions\SupplierBills\CreateSupplierBill;
use App\Actions\SupplierBills\UpdateDraftSupplierBill;
use App\Models\Member;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\SupplierBill;
use App\Models\Store;
use App\Models\VirtualStockIntake;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public ?int $supplierBillId = null;
    public int $supplierId = 0;
    public ?int $storeId = null;
    public ?int $purchaseOrderId = null;
    public string $documentNumber = '';
    public string $currencyCode = 'EUR';
    public ?string $dueAt = null;
    public ?string $reference = null;
    public ?string $notes = null;

    /** @var list<array<string, mixed>> */
    public array $lines = [];

    public function mount(?SupplierBill $supplierBill = null): void
    {
        if ($supplierBill?->exists) {
            Gate::authorize('update', $supplierBill);
            $this->supplierBillId = $supplierBill->id;
            $this->supplierId = $supplierBill->supplier_id;
            $this->storeId = $supplierBill->store_id;
            $this->purchaseOrderId = $supplierBill->purchase_order_id;
            $this->documentNumber = $supplierBill->document_number;
            $this->currencyCode = $supplierBill->currency_code;
            $this->dueAt = $supplierBill->due_at?->format('Y-m-d');
            $this->reference = $supplierBill->reference;
            $this->notes = $supplierBill->notes;
            $this->lines = $supplierBill->items->map(fn ($line): array => [
                'product_id' => $line->product_id,
                'purchase_order_item_id' => $line->purchase_order_item_id,
                'virtual_stock_intake_id' => $line->virtual_stock_intake_id,
                'description' => $line->description,
                'quantity' => (string) $line->quantity,
                'unit_cost' => number_format($line->unit_cost / 100, 2, '.', ''),
                'tax' => number_format($line->tax / 100, 2, '.', ''),
            ])->values()->all();
        } else {
            Gate::authorize('create', SupplierBill::class);
            $this->lines = [['description' => '', 'quantity' => '1.00', 'unit_cost' => '0.00', 'tax' => '0.00', 'purchase_order_item_id' => null, 'virtual_stock_intake_id' => null]];
        }
    }

    public function addLine(): void
    {
        $this->lines[] = ['description' => '', 'quantity' => '1.00', 'unit_cost' => '0.00', 'tax' => '0.00', 'purchase_order_item_id' => null, 'virtual_stock_intake_id' => null];
    }

    public function removeLine(int $index): void
    {
        if (count($this->lines) <= 1) {
            return;
        }

        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function save(): void
    {
        $this->validate([
            'supplierId' => ['required', 'integer', Rule::exists('members', 'id')],
            'storeId' => ['nullable', 'integer', Rule::exists('stores', 'id')],
            'purchaseOrderId' => ['nullable', 'integer', Rule::exists('purchase_orders', 'id')],
            'documentNumber' => ['required', 'string', 'max:100'],
            'dueAt' => ['nullable', 'date'],
            'currencyCode' => ['required', 'string', 'size:3'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'lines' => ['array', 'min:1'],
            'lines.*.description' => ['required', 'string', 'max:500'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'lines.*.tax' => ['nullable', 'numeric', 'min:0'],
            'lines.*.product_id' => ['nullable', 'integer', Rule::exists('products', 'id')],
            'lines.*.purchase_order_item_id' => ['nullable', 'integer', Rule::exists('purchase_order_items', 'id')],
            'lines.*.virtual_stock_intake_id' => ['nullable', 'integer', Rule::exists('virtual_stock_intakes', 'id')],
        ]);

        $lines = array_map(fn (array $line): array => [
            'product_id' => filled($line['product_id'] ?? null) ? (int) $line['product_id'] : null,
            'purchase_order_item_id' => filled($line['purchase_order_item_id'] ?? null) ? (int) $line['purchase_order_item_id'] : null,
            'virtual_stock_intake_id' => filled($line['virtual_stock_intake_id'] ?? null) ? (int) $line['virtual_stock_intake_id'] : null,
            'description' => $line['description'],
            'quantity' => bcadd((string) $line['quantity'], '0', 2),
            'unit_cost' => (int) round((float) $line['unit_cost'] * 100),
            'tax' => (int) round((float) ($line['tax'] ?? 0) * 100),
        ], $this->lines);

        $attributes = [
            'supplier_id' => $this->supplierId,
            'store_id' => $this->storeId,
            'purchase_order_id' => $this->purchaseOrderId,
            'document_number' => $this->documentNumber,
            'due_at' => $this->dueAt ?: null,
            'currency_code' => strtoupper($this->currencyCode),
            'exchange_rate' => '1',
            'reference' => $this->reference,
            'notes' => $this->notes,
        ];

        $bill = $this->supplierBillId === null
            ? app(CreateSupplierBill::class)($attributes, $lines)
            : app(UpdateDraftSupplierBill::class)(SupplierBill::query()->findOrFail($this->supplierBillId), $attributes, $lines);

        $this->redirect(route('supplier-bills.show', $bill), navigate: true);
    }

    public function with(): array
    {
        return [
            'suppliers' => Member::query()->where('membership_type', 'organisation')->orderBy('name')->get(['id', 'name']),
            'stores' => Store::query()->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()->orderBy('name')->limit(200)->get(['id', 'name']),
            'purchaseOrders' => PurchaseOrder::query()->orderByDesc('id')->limit(200)->get(['id', 'number']),
            'purchaseOrderItems' => PurchaseOrderItem::query()->with('purchaseOrder')->orderByDesc('id')->limit(300)->get(),
            'virtualIntakes' => VirtualStockIntake::query()->with('product')->orderByDesc('id')->limit(300)->get(),
            'isEditing' => $this->supplierBillId !== null,
        ];
    }
}; ?>

<section class="w-full">
    <x-dense::page-header :title="$isEditing ? 'Edit Draft Supplier Bill' : 'New Supplier Bill'" />
    <div class="max-w-5xl px-6 py-4 max-md:px-5 max-sm:px-3">
        <form wire:submit="save" class="space-y-6">
            <div class="grid gap-4 md:grid-cols-3">
                <flux:field><flux:label>Supplier</flux:label><flux:select wire:model="supplierId"><option value="0">Select supplier</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->name }}</option>@endforeach</flux:select><flux:error name="supplierId" /></flux:field>
                <flux:field><flux:label>Document number</flux:label><flux:input wire:model="documentNumber" /><flux:error name="documentNumber" /></flux:field>
                <flux:field><flux:label>Currency</flux:label><flux:input wire:model="currencyCode" maxlength="3" /></flux:field>
                <flux:field><flux:label>Due date</flux:label><flux:input wire:model="dueAt" type="date" /></flux:field>
                <flux:field><flux:label>Purchase Order</flux:label><flux:select wire:model="purchaseOrderId"><option value="">None</option>@foreach($purchaseOrders as $po)<option value="{{ $po->id }}">{{ $po->number }}</option>@endforeach</flux:select></flux:field>
                <flux:field><flux:label>Reference</flux:label><flux:input wire:model="reference" /></flux:field>
            </div>
            <flux:field><flux:label>Notes</flux:label><flux:textarea wire:model="notes" rows="3" /></flux:field>
            <x-dense::card title="Bill lines">
                <x-signals.table-wrap><table class="s-table"><thead><tr><th>Product</th><th>Description</th><th>Qty</th><th>Unit cost</th><th>Tax</th><th>PO item</th><th>Virtual intake</th><th></th></tr></thead><tbody>
                @foreach($lines as $index => $line)
                    <tr wire:key="supplier-line-{{ $index }}"><td><flux:select wire:model="lines.{{ $index }}.product_id"><option value="">Free-text</option>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->name }}</option>@endforeach</flux:select></td><td><flux:input wire:model="lines.{{ $index }}.description" /></td><td><flux:input wire:model="lines.{{ $index }}.quantity" /></td><td><flux:input wire:model="lines.{{ $index }}.unit_cost" /></td><td><flux:input wire:model="lines.{{ $index }}.tax" /></td><td><flux:select wire:model="lines.{{ $index }}.purchase_order_item_id"><option value="">None</option>@foreach($purchaseOrderItems as $item)<option value="{{ $item->id }}">{{ $item->purchaseOrder?->number }} #{{ $item->id }}</option>@endforeach</flux:select></td><td><flux:select wire:model="lines.{{ $index }}.virtual_stock_intake_id"><option value="">None</option>@foreach($virtualIntakes as $intake)<option value="{{ $intake->id }}">{{ $intake->number }} / {{ $intake->product?->name }}</option>@endforeach</flux:select></td><td><button type="button" wire:click="removeLine({{ $index }})" class="s-btn s-btn-sm s-btn-ghost">Remove</button></td></tr>
                @endforeach
                </tbody></table></x-signals.table-wrap>
                <button type="button" wire:click="addLine" class="s-btn s-btn-sm s-btn-secondary mt-3">Add line</button>
            </x-dense::card>
            <div class="flex gap-2"><flux:button type="submit" variant="primary">Save draft</flux:button><a href="{{ route('supplier-bills.index') }}" wire:navigate.hover class="s-btn s-btn-secondary">Cancel</a></div>
        </form>
    </div>
</section>
