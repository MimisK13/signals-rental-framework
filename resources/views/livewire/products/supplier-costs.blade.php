<?php

use App\Actions\SupplierCosts\CreateSupplierCost;
use App\Actions\SupplierCosts\DeleteSupplierCost;
use App\Actions\SupplierCosts\SetPreferredSupplier;
use App\Actions\SupplierCosts\UpdateSupplierCost;
use App\Data\SupplierCosts\CreateSupplierCostData;
use App\Data\SupplierCosts\UpdateSupplierCostData;
use App\Enums\MembershipType;
use App\Models\Currency;
use App\Models\Member;
use App\Models\Product;
use App\Models\ProductSupplierCost;
use App\Models\Store;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Product $product;

    public bool $showFormModal = false;

    public ?int $editingCostId = null;

    public ?int $supplierId = null;

    public ?int $storeId = null;

    public string $costBasis = 'per_unit';

    public string $cost = '';

    public string $currencyCode = 'GBP';

    public ?string $supplierSku = null;

    public ?int $leadTimeDays = null;

    public string $minOrderQuantity = '1.00';

    public bool $isPreferred = false;

    public function mount(Product $product): void
    {
        Gate::authorize('viewAny', ProductSupplierCost::class);

        $this->product = $product;
        $this->currencyCode = (string) (settings('company.base_currency') ?? 'GBP');
        $this->loadCosts();
    }

    public function rendering(View $view): void
    {
        $view->title($this->product->name.' — Supplier Costs');
    }

    public function openCreateModal(): void
    {
        Gate::authorize('create', ProductSupplierCost::class);

        $this->resetForm();
        $this->showFormModal = true;
    }

    public function openEditModal(int $costId): void
    {
        $cost = $this->product->supplierCosts()->findOrFail($costId);
        Gate::authorize('update', $cost);

        $this->editingCostId = $cost->id;
        $this->supplierId = $cost->supplier_id;
        $this->storeId = $cost->store_id;
        $this->costBasis = $cost->cost_basis;
        $this->cost = (string) Money::ofMinor($cost->cost, $cost->currency_code)->getAmount();
        $this->currencyCode = $cost->currency_code;
        $this->supplierSku = $cost->supplier_sku;
        $this->leadTimeDays = $cost->lead_time_days;
        $this->minOrderQuantity = bcadd((string) $cost->min_order_quantity, '0', 2);
        $this->isPreferred = $cost->is_preferred;
        $this->showFormModal = true;
    }

    public function save(): void
    {
        $costBases = array_keys(config('supplier_costs.cost_bases', []));

        $this->validate([
            'supplierId' => [
                'required',
                'integer',
                Rule::exists('members', 'id')->where('membership_type', MembershipType::Organisation->value),
            ],
            'storeId' => ['nullable', 'integer', 'exists:stores,id'],
            'costBasis' => ['required', 'string', Rule::in($costBases)],
            'cost' => ['required', 'numeric', 'min:0'],
            'currencyCode' => ['required', 'string', 'size:3'],
            'supplierSku' => ['nullable', 'string', 'max:255'],
            'leadTimeDays' => ['nullable', 'integer', 'min:0'],
            'minOrderQuantity' => ['required', 'numeric', 'min:0.01'],
            'isPreferred' => ['boolean'],
        ]);

        $minor = Money::of($this->cost, $this->currencyCode, roundingMode: RoundingMode::HALF_UP)
            ->getMinorAmount()
            ->toInt();

        if ($this->editingCostId !== null) {
            $cost = $this->product->supplierCosts()->findOrFail($this->editingCostId);

            (new UpdateSupplierCost)($cost, UpdateSupplierCostData::from([
                'supplier_id' => $this->supplierId,
                'store_id' => $this->storeId,
                'store_id_provided' => true,
                'cost_basis' => $this->costBasis,
                'cost' => $minor,
                'currency_code' => $this->currencyCode,
                'supplier_sku' => $this->supplierSku,
                'supplier_sku_provided' => true,
                'lead_time_days' => $this->leadTimeDays,
                'lead_time_days_provided' => true,
                'min_order_quantity' => $this->minOrderQuantity,
                'is_preferred' => $this->isPreferred,
            ]));
        } else {
            (new CreateSupplierCost)(CreateSupplierCostData::from([
                'product_id' => $this->product->id,
                'supplier_id' => $this->supplierId,
                'store_id' => $this->storeId,
                'cost_basis' => $this->costBasis,
                'cost' => $minor,
                'currency_code' => $this->currencyCode,
                'supplier_sku' => $this->supplierSku,
                'lead_time_days' => $this->leadTimeDays,
                'min_order_quantity' => $this->minOrderQuantity,
                'is_preferred' => $this->isPreferred,
            ]));
        }

        $this->showFormModal = false;
        $this->resetForm();
        $this->loadCosts();
        $this->dispatch('supplier-cost-saved');
    }

    public function setPreferred(int $costId): void
    {
        $cost = $this->product->supplierCosts()->findOrFail($costId);

        (new SetPreferredSupplier)($cost);

        $this->loadCosts();
        $this->dispatch('supplier-cost-preferred');
    }

    public function deleteCost(int $costId): void
    {
        $cost = $this->product->supplierCosts()->findOrFail($costId);

        (new DeleteSupplierCost)($cost);

        $this->loadCosts();
        $this->dispatch('supplier-cost-deleted');
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $costBases = config('supplier_costs.cost_bases', []);

        return [
            'costs' => $this->product->supplierCosts()
                ->with(['supplier', 'store'])
                ->orderByDesc('is_preferred')
                ->orderBy('supplier_id')
                ->get(),
            'suppliers' => Member::query()
                ->where('membership_type', MembershipType::Organisation)
                ->orderBy('name')
                ->get(['id', 'name']),
            'stores' => Store::query()->orderBy('name')->get(['id', 'name']),
            'costBases' => $costBases,
            'currencies' => Currency::query()->enabled()->orderBy('code')->get(['code', 'name']),
            'canManage' => auth()->user()?->can('products.supplier_costs') ?? false,
        ];
    }

    private function resetForm(): void
    {
        $this->editingCostId = null;
        $this->supplierId = null;
        $this->storeId = null;
        $this->costBasis = 'per_unit';
        $this->cost = '';
        $this->currencyCode = (string) (settings('company.base_currency') ?? 'GBP');
        $this->supplierSku = null;
        $this->leadTimeDays = null;
        $this->minOrderQuantity = '1.00';
        $this->isPreferred = false;
        $this->resetValidation();
    }

    private function loadCosts(): void
    {
        $this->product->loadCount(['stockLevels', 'accessories', 'attachments', 'rates', 'supplierCosts', 'activities']);
    }
}; ?>

<section class="w-full">
    @include('livewire.products.partials.product-header', ['product' => $product, 'subpage' => 'Supplier Costs'])
    @include('livewire.products.partials.product-tabs', ['product' => $product, 'activeTab' => 'supplier-costs'])

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-[var(--text-secondary)]" style="font-family: var(--font-display); text-transform: uppercase; letter-spacing: 0.04em;">
                Supplier Costs ({{ $product->supplier_costs_count ?? $costs->count() }})
            </h3>
            @if($canManage)
                <button type="button" wire:click="openCreateModal" class="s-btn s-btn-sm s-btn-accent">
                    <flux:icon.plus class="w-4 h-4" />
                    Add Supplier Cost
                </button>
            @endif
        </div>

        <x-action-message on="supplier-cost-saved">Supplier cost saved.</x-action-message>
        <x-action-message on="supplier-cost-preferred">Preferred supplier updated.</x-action-message>
        <x-action-message on="supplier-cost-deleted">Supplier cost removed.</x-action-message>

        @if($costs->isNotEmpty())
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
                            @if($canManage)
                                <th style="width: 48px;"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($costs as $cost)
                            <tr wire:key="supplier-cost-{{ $cost->id }}">
                                <td class="font-medium">{{ $cost->supplier?->name ?? '—' }}</td>
                                <td>{{ $costBases[$cost->cost_basis]['label'] ?? $cost->cost_basis }}</td>
                                <td class="text-left">
                                    <span class="s-badge s-badge-outline">{{ $cost->formatMoneyCost('cost') }} {{ $cost->currency_code }}</span>
                                </td>
                                <td class="text-sm text-[var(--text-secondary)]">{{ $cost->supplier_sku ?? '—' }}</td>
                                <td class="s-cell-mono">{{ $cost->lead_time_days !== null ? $cost->lead_time_days.'d' : '—' }}</td>
                                <td class="s-cell-mono">{{ bcadd((string) $cost->min_order_quantity, '0', 2) }}</td>
                                <td>{{ $cost->store?->name ?? 'All stores' }}</td>
                                <td>
                                    @if($cost->is_preferred)
                                        <span class="s-badge s-badge-green">Preferred</span>
                                    @elseif($canManage)
                                        <button type="button" wire:click="setPreferred({{ $cost->id }})" class="s-btn s-btn-xs s-btn-ghost">Set preferred</button>
                                    @else
                                        <span class="text-[var(--text-secondary)]">—</span>
                                    @endif
                                </td>
                                @if($canManage)
                                    <td class="text-right">
                                        <div x-data="{ open: false }" class="relative inline-flex">
                                            <button type="button" x-on:click.stop="open = !open" class="s-btn-ghost s-btn-xs s-btn-icon">
                                                <flux:icon.ellipsis-vertical class="w-4 h-4" />
                                            </button>
                                            <div
                                                x-show="open"
                                                x-on:click.outside="open = false"
                                                x-transition
                                                x-cloak
                                                class="s-dropdown"
                                                style="position: fixed; z-index: 9999;"
                                                x-ref="dropdown"
                                                x-init="$watch('open', value => {
                                                    if (value) {
                                                        $nextTick(() => {
                                                            const rect = $el.previousElementSibling.getBoundingClientRect();
                                                            $refs.dropdown.style.top = rect.bottom + 4 + 'px';
                                                            $refs.dropdown.style.right = (window.innerWidth - rect.right) + 'px';
                                                            $refs.dropdown.style.left = 'auto';
                                                        });
                                                    }
                                                })"
                                            >
                                                <button type="button" wire:click="openEditModal({{ $cost->id }})" x-on:click="open = false" class="s-dropdown-item" style="width: 100%;">
                                                    <flux:icon.pencil-square class="w-3.5 h-3.5" />
                                                    Edit
                                                </button>
                                                <div style="height: 1px; background: var(--card-border); margin: 4px 0;"></div>
                                                <button type="button" x-on:click="open = false; $dispatch('open-modal', 'remove-supplier-cost-{{ $cost->id }}')" class="s-dropdown-item" style="color: var(--red); width: 100%;">
                                                    <flux:icon.trash class="w-3.5 h-3.5" />
                                                    Remove
                                                </button>
                                            </div>
                                        </div>

                                        <x-signals.modal name="remove-supplier-cost-{{ $cost->id }}" title="Remove Supplier Cost" size="sm">
                                            <p>Remove the supplier cost for <strong>{{ $cost->supplier?->name ?? 'this supplier' }}</strong>? This action cannot be undone.</p>

                                            <x-slot:footer>
                                                <button type="button" x-on:click="$dispatch('close-modal', 'remove-supplier-cost-{{ $cost->id }}')" class="s-btn s-btn-ghost">Cancel</button>
                                                <button type="button" wire:click="deleteCost({{ $cost->id }})" x-on:click="$dispatch('close-modal', 'remove-supplier-cost-{{ $cost->id }}')" class="s-btn s-btn-danger">Remove</button>
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
            <x-signals.empty title="No Supplier Costs" description="Add supplier costs to track sub-hire purchase pricing for this product.">
                @if($canManage)
                    <button type="button" wire:click="openCreateModal" class="s-btn s-btn-sm s-btn-accent">Add Supplier Cost</button>
                @endif
            </x-signals.empty>
        @endif
    </div>

    <flux:modal wire:model="showFormModal" class="max-w-lg">
        <div class="space-y-4">
            <flux:heading size="lg">{{ $editingCostId ? 'Edit Supplier Cost' : 'Add Supplier Cost' }}</flux:heading>

            <flux:field>
                <flux:label>Supplier</flux:label>
                <flux:select wire:model="supplierId" placeholder="Select supplier…">
                    @foreach($suppliers as $supplier)
                        <flux:select.option wire:key="supplier-opt-{{ $supplier->id }}" value="{{ $supplier->id }}">{{ $supplier->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="supplierId" />
            </flux:field>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Cost basis</flux:label>
                    <flux:select wire:model="costBasis">
                        @foreach($costBases as $key => $basis)
                            <flux:select.option wire:key="basis-opt-{{ $key }}" value="{{ $key }}">{{ $basis['label'] }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="costBasis" />
                </flux:field>

                <flux:field>
                    <flux:label>Store</flux:label>
                    <flux:select wire:model="storeId" placeholder="All stores">
                        <flux:select.option value="">All stores</flux:select.option>
                        @foreach($stores as $store)
                            <flux:select.option wire:key="store-opt-{{ $store->id }}" value="{{ $store->id }}">{{ $store->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="storeId" />
                </flux:field>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Cost</flux:label>
                    <flux:input wire:model="cost" type="text" inputmode="decimal" />
                    <flux:error name="cost" />
                </flux:field>

                <flux:field>
                    <flux:label>Currency</flux:label>
                    <flux:select wire:model="currencyCode">
                        @foreach($currencies as $currency)
                            <flux:select.option wire:key="currency-opt-{{ $currency->code }}" value="{{ $currency->code }}">{{ $currency->code }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="currencyCode" />
                </flux:field>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Supplier SKU</flux:label>
                    <flux:input wire:model="supplierSku" />
                    <flux:error name="supplierSku" />
                </flux:field>

                <flux:field>
                    <flux:label>Lead time (days)</flux:label>
                    <flux:input wire:model="leadTimeDays" type="number" min="0" />
                    <flux:error name="leadTimeDays" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Min order quantity</flux:label>
                <flux:input wire:model="minOrderQuantity" type="text" inputmode="decimal" />
                <flux:error name="minOrderQuantity" />
            </flux:field>

            <flux:field variant="inline">
                <flux:checkbox wire:model="isPreferred" />
                <flux:label>Preferred supplier for this store scope and cost basis</flux:label>
            </flux:field>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button variant="ghost" wire:click="$set('showFormModal', false)">Cancel</flux:button>
                <flux:button variant="primary" wire:click="save" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">Save</span>
                    <span wire:loading wire:target="save">Saving…</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</section>
