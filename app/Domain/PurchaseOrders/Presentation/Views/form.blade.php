<section class="w-full">
    <x-dense::page-header :title="$isEditing ? 'Edit Draft Purchase Order' : 'New Draft Purchase Order'">
        <x-slot:breadcrumbs>
            <a href="{{ route('purchase-orders.index') }}" wire:navigate.hover class="text-[var(--link)] hover:underline">Purchase Orders</a>
            <span class="mx-1 text-[var(--text-muted)]">/</span>
            <span>{{ $isEditing ? 'Edit' : 'Create' }}</span>
        </x-slot:breadcrumbs>
    </x-dense::page-header>

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3 max-w-4xl">
        <form wire:submit="save" class="space-y-6">
            <div class="grid gap-4 md:grid-cols-2">
                <flux:field>
                    <flux:label>Supplier (Organisation)</flux:label>
                    <flux:select wire:model="supplierId">
                        <option value="0">Select supplier…</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="supplierId" />
                </flux:field>

                <flux:field>
                    <flux:label>Store</flux:label>
                    <flux:select wire:model="storeId">
                        @foreach($stores as $store)
                            <option value="{{ $store->id }}">{{ $store->name }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="storeId" />
                </flux:field>

                <flux:field>
                    <flux:label>Type</flux:label>
                    <flux:select wire:model="poType">
                        @foreach($poTypes as $typeKey => $typeMeta)
                            <option value="{{ $typeKey }}">{{ $typeMeta['label'] ?? $typeKey }}</option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>Reference</flux:label>
                    <flux:input wire:model="reference" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Notes</flux:label>
                <flux:textarea wire:model="notes" rows="3" />
            </flux:field>

            <x-dense::card title="Line items">
                <p class="mb-3 text-xs text-[var(--text-muted)]">Leave unit cost blank on product lines to pre-fill from the preferred per-unit supplier cost.</p>
                <x-signals.table-wrap>
                    <table class="s-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Description</th>
                                <th class="w-24">Qty</th>
                                <th class="w-32">Unit cost</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($lines as $index => $line)
                                <tr wire:key="line-{{ $index }}-{{ $line['id'] ?? 'new' }}">
                                    <td>
                                        <flux:select wire:model="lines.{{ $index }}.product_id">
                                            <option value="">—</option>
                                            @foreach($products as $product)
                                                <option value="{{ $product->id }}">{{ $product->name }}</option>
                                            @endforeach
                                        </flux:select>
                                    </td>
                                    <td><flux:input wire:model="lines.{{ $index }}.description" /></td>
                                    <td><flux:input wire:model="lines.{{ $index }}.quantity" /></td>
                                    <td><flux:input wire:model="lines.{{ $index }}.unit_cost" placeholder="Auto" /></td>
                                    <td>
                                        <button type="button" wire:click="removeLine({{ $index }})" class="s-btn s-btn-sm s-btn-ghost">Remove</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-signals.table-wrap>
                <button type="button" wire:click="addLine" class="s-btn s-btn-sm s-btn-secondary mt-3">Add line</button>
            </x-dense::card>

            <div class="flex gap-2">
                <flux:button type="submit" variant="primary">Save draft</flux:button>
                <a href="{{ route('purchase-orders.index') }}" wire:navigate.hover class="s-btn s-btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</section>
