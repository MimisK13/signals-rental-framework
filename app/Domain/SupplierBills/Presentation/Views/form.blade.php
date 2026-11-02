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
