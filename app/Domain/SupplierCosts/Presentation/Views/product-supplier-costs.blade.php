<section class="w-full">
    @include('products::partials.product-header', [
        'product' => $product,
        'subpage' => 'Supplier Costs',
    ])
    @include('products::partials.product-tabs', [
        'product' => $product,
        'activeTab' => 'supplier-costs',
    ])

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <h3
                class="text-sm font-semibold text-[var(--text-secondary)]"
                style="font-family: var(--font-display); text-transform: uppercase; letter-spacing: 0.04em;"
            >
                Supplier Costs ({{ $product->supplier_costs_count ?? $costs->count() }})
            </h3>
            @if ($canManage)
                <button
                    type="button"
                    wire:click="openCreateModal"
                    class="s-btn s-btn-sm s-btn-accent"
                >
                    <flux:icon.plus class="w-4 h-4" />
                    Add Supplier Cost
                </button>
            @endif
        </div>

        <x-action-message on="supplier-cost-saved">Supplier cost saved.</x-action-message>
        <x-action-message on="supplier-cost-preferred">Preferred supplier updated.</x-action-message>
        <x-action-message on="supplier-cost-deleted">Supplier cost removed.</x-action-message>

        @if ($costs->isNotEmpty())
            <div class="s-table-wrap">
                <table class="s-table s-table-compact w-full">
                    <thead>
                        <tr>
                            <th class="text-left">Supplier</th>
                            <th class="text-left">Basis</th>
                            <th class="text-left">Cost</th>
                            <th class="text-left">SKU</th>
                            <th class="text-left">Lead Time</th>
                            <th class="text-left">MOQ</th>
                            <th class="text-left">Store</th>
                            <th class="text-left">Preferred</th>
                            @if ($canManage)
                                <th style="width: 48px;"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($costs as $cost)
                            <tr wire:key="supplier-cost-{{ $cost->id }}">
                                <td class="font-medium">{{ $cost->supplier?->name ?? '—' }}</td>
                                <td>{{ $costBases[$cost->cost_basis]['label'] ?? $cost->cost_basis }}</td>
                                <td class="text-left">
                                    <span class="s-badge s-badge-outline">{{ $cost->formatMoneyCost('cost') }}
                                        {{ $cost->currency_code }}</span>
                                </td>
                                <td class="text-sm text-[var(--text-secondary)]">{{ $cost->supplier_sku ?? '—' }}</td>
                                <td class="s-cell-mono">
                                    {{ $cost->lead_time_days !== null ? $cost->lead_time_days . 'd' : '—' }}</td>
                                <td class="s-cell-mono">{{ bcadd((string) $cost->min_order_quantity, '0', 2) }}</td>
                                <td>{{ $cost->store?->name ?? 'All stores' }}</td>
                                <td>
                                    @if ($cost->is_preferred)
                                        <span class="s-badge s-badge-green">Preferred</span>
                                    @elseif($canManage)
                                        <button
                                            type="button"
                                            wire:click="setPreferred({{ $cost->id }})"
                                            class="s-btn s-btn-xs s-btn-ghost"
                                        >Set preferred</button>
                                    @else
                                        <span class="text-[var(--text-secondary)]">—</span>
                                    @endif
                                </td>
                                @if ($canManage)
                                    <td class="text-right">
                                        <flux:dropdown>
                                            <flux:button
                                                size="sm"
                                                variant="ghost"
                                                icon="ellipsis-horizontal"
                                                aria-label="Actions for {{ $cost->supplier?->name ?? 'supplier cost' }}"
                                            />
                                            <flux:menu>
                                                <flux:menu.item wire:click="openEditModal({{ $cost->id }})">
                                                    Edit
                                                </flux:menu.item>
                                                <flux:menu.item
                                                    variant="danger"
                                                    x-on:click="$dispatch('open-modal', 'remove-supplier-cost-{{ $cost->id }}')"
                                                >
                                                    Remove
                                                </flux:menu.item>
                                            </flux:menu>
                                        </flux:dropdown>

                                        <x-signals.modal
                                            name="remove-supplier-cost-{{ $cost->id }}"
                                            title="Remove Supplier Cost"
                                            size="sm"
                                        >
                                            <p>Remove the supplier cost for
                                                <strong>{{ $cost->supplier?->name ?? 'this supplier' }}</strong>? This
                                                action cannot be undone.
                                            </p>

                                            <x-slot:footer>
                                                <button
                                                    type="button"
                                                    x-on:click="$dispatch('close-modal', 'remove-supplier-cost-{{ $cost->id }}')"
                                                    class="s-btn s-btn-ghost"
                                                >Cancel</button>
                                                <button
                                                    type="button"
                                                    wire:click="deleteCost({{ $cost->id }})"
                                                    x-on:click="$dispatch('close-modal', 'remove-supplier-cost-{{ $cost->id }}')"
                                                    class="s-btn s-btn-danger"
                                                >Remove</button>
                                            </x-slot:footer>
                                        </x-signals.modal>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-dense::empty-state
                title="No Supplier Costs"
                description="Add supplier costs to track sub-hire purchase pricing for this product."
            >
                @if ($canManage)
                    <button
                        type="button"
                        wire:click="openCreateModal"
                        class="s-btn s-btn-sm s-btn-accent"
                    >Add Supplier Cost</button>
                @endif
            </x-dense::empty-state>
        @endif
    </div>

    <flux:modal
        wire:model="showFormModal"
        class="max-w-lg"
    >
        <div class="space-y-4">
            <flux:heading size="lg">{{ $editingCostId ? 'Edit Supplier Cost' : 'Add Supplier Cost' }}
            </flux:heading>

            <flux:field>
                <flux:label>Supplier</flux:label>
                <flux:select
                    wire:model="supplierId"
                    placeholder="Select supplier…"
                >
                    @foreach ($suppliers as $supplier)
                        <flux:select.option
                            wire:key="supplier-opt-{{ $supplier->id }}"
                            value="{{ $supplier->id }}"
                        >{{ $supplier->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="supplierId" />
            </flux:field>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Cost basis</flux:label>
                    <flux:select wire:model="costBasis">
                        @foreach ($costBases as $key => $basis)
                            <flux:select.option
                                wire:key="basis-opt-{{ $key }}"
                                value="{{ $key }}"
                            >{{ $basis['label'] }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="costBasis" />
                </flux:field>

                <flux:field>
                    <flux:label>Store</flux:label>
                    <flux:select
                        wire:model="storeId"
                        placeholder="All stores"
                    >
                        <flux:select.option value="">All stores</flux:select.option>
                        @foreach ($stores as $store)
                            <flux:select.option
                                wire:key="store-opt-{{ $store->id }}"
                                value="{{ $store->id }}"
                            >{{ $store->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="storeId" />
                </flux:field>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Cost</flux:label>
                    <flux:input
                        wire:model="cost"
                        type="text"
                        inputmode="decimal"
                    />
                    <flux:error name="cost" />
                </flux:field>

                <flux:field>
                    <flux:label>Currency</flux:label>
                    <flux:select wire:model="currencyCode">
                        @foreach ($currencies as $currency)
                            <flux:select.option
                                wire:key="currency-opt-{{ $currency->code }}"
                                value="{{ $currency->code }}"
                            >{{ $currency->code }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="currencyCode" />
                </flux:field>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Supplier SKU</flux:label>
                    <flux:input wire:model="supplierSku" />
                    <flux:error name="supplierSku" />
                </flux:field>

                <flux:field>
                    <flux:label>Lead time (days)</flux:label>
                    <flux:input
                        wire:model="leadTimeDays"
                        type="number"
                        min="0"
                    />
                    <flux:error name="leadTimeDays" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Min order quantity</flux:label>
                <flux:input
                    wire:model="minOrderQuantity"
                    type="text"
                    inputmode="decimal"
                />
                <flux:error name="minOrderQuantity" />
            </flux:field>

            <flux:field variant="inline">
                <flux:checkbox wire:model="isPreferred" />
                <flux:label>Preferred supplier for this store scope and cost basis</flux:label>
            </flux:field>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button
                    variant="ghost"
                    wire:click="$set('showFormModal', false)"
                >Cancel</flux:button>
                <flux:button
                    variant="primary"
                    wire:click="save"
                    wire:loading.attr="disabled"
                >
                    <span
                        wire:loading.remove
                        wire:target="save"
                    >Save</span>
                    <span
                        wire:loading
                        wire:target="save"
                    >Saving…</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</section>
