<?php

use App\Actions\PurchaseOrders\CreatePurchaseOrder;
use App\Actions\PurchaseOrders\UpdateDraftPurchaseOrder;
use App\Data\PurchaseOrders\CreatePurchaseOrderData;
use App\Data\PurchaseOrders\UpdateDraftPurchaseOrderData;
use App\Enums\MembershipType;
use App\Models\Member;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Store;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public ?int $purchaseOrderId = null;

    public int $supplierId = 0;

    public int $storeId = 0;

    public string $poType = 'purchase';

    public ?string $reference = null;

    public ?string $notes = null;

    /** @var list<array{id?: int, product_id?: int, description: string, quantity: string, unit_cost?: string}> */
    public array $lines = [];

    public function mount(?PurchaseOrder $purchaseOrder = null): void
    {
        if ($purchaseOrder !== null) {
            Gate::authorize('update', $purchaseOrder);
            $purchaseOrder->ensureDraft();
            $purchaseOrder->load('items');

            $this->purchaseOrderId = $purchaseOrder->id;
            $this->supplierId = $purchaseOrder->supplier_id;
            $this->storeId = $purchaseOrder->store_id;
            $this->poType = $purchaseOrder->po_type;
            $this->reference = $purchaseOrder->reference;
            $this->notes = $purchaseOrder->notes;
            $this->lines = $purchaseOrder->items->map(fn ($item): array => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'description' => $item->description,
                'quantity' => (string) $item->quantity,
                'unit_cost' => $item->formatMoneyCost('unit_cost'),
            ])->values()->all();
        } else {
            Gate::authorize('create', PurchaseOrder::class);
            $defaultStore = Store::query()->orderBy('id')->value('id');
            $this->storeId = (int) ($defaultStore ?? 0);
            $this->lines = [
                ['description' => '', 'quantity' => '1.00'],
            ];
        }
    }

    public function addLine(): void
    {
        $this->lines[] = ['description' => '', 'quantity' => '1.00'];
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
            'supplierId' => ['required', 'integer', Rule::exists('members', 'id')->where('membership_type', MembershipType::Organisation->value)],
            'storeId' => ['required', 'integer', Rule::exists('stores', 'id')],
            'poType' => ['required', Rule::in(array_keys(config('purchase_orders.types', [])))],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'lines' => ['array', 'min:1'],
            'lines.*.description' => ['required', 'string', 'max:500'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'lines.*.product_id' => ['nullable', 'integer', Rule::exists('products', 'id')],
        ]);

        $payloadLines = array_map(function (array $line): array {
            $row = [
                'description' => $line['description'],
                'quantity' => bcadd((string) $line['quantity'], '0', 2),
                'product_id' => ! empty($line['product_id']) ? (int) $line['product_id'] : null,
            ];

            if (isset($line['id'])) {
                $row['id'] = (int) $line['id'];
            }

            if (isset($line['unit_cost']) && $line['unit_cost'] !== '' && $line['unit_cost'] !== null) {
                $row['unit_cost'] = (int) round(((float) $line['unit_cost']) * 100);
            }

            return $row;
        }, $this->lines);

        if ($this->purchaseOrderId !== null) {
            $purchaseOrder = PurchaseOrder::query()->findOrFail($this->purchaseOrderId);
            (new UpdateDraftPurchaseOrder)($purchaseOrder, UpdateDraftPurchaseOrderData::from([
                'supplier_id' => $this->supplierId,
                'store_id' => $this->storeId,
                'po_type' => $this->poType,
                'reference' => $this->reference,
                'notes' => $this->notes,
                'lines' => $payloadLines,
            ]));
            $this->redirect(route('purchase-orders.show', $purchaseOrder), navigate: true);

            return;
        }

        $result = (new CreatePurchaseOrder)(CreatePurchaseOrderData::from([
            'supplier_id' => $this->supplierId,
            'store_id' => $this->storeId,
            'po_type' => $this->poType,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'lines' => $payloadLines,
        ]));

        $this->redirect(route('purchase-orders.show', $result->id), navigate: true);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'isEditing' => $this->purchaseOrderId !== null,
            'suppliers' => Member::query()->where('membership_type', MembershipType::Organisation->value)->orderBy('name')->get(['id', 'name']),
            'stores' => Store::query()->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()->orderBy('name')->limit(200)->get(['id', 'name']),
            'poTypes' => config('purchase_orders.types', []),
        ];
    }
}; ?>

<section class="w-full">
    <x-signals.page-header :title="$isEditing ? 'Edit Draft Purchase Order' : 'New Draft Purchase Order'">
        <x-slot:breadcrumbs>
            <a href="{{ route('purchase-orders.index') }}" wire:navigate class="text-[var(--link)] hover:underline">Purchase Orders</a>
            <span class="mx-1 text-[var(--text-muted)]">/</span>
            <span>{{ $isEditing ? 'Edit' : 'Create' }}</span>
        </x-slot:breadcrumbs>
    </x-signals.page-header>

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3 max-w-4xl">
        <form wire:submit="save" class="space-y-6">
            <div class="grid gap-4 md:grid-cols-2">
                <flux:field>
                    <flux:label>Supplier (Organisation)</flux:label>
                    <flux:select wire:model="supplierId">
                        <flux:select.option value="0">Select supplier…</flux:select.option>
                        @foreach($suppliers as $supplier)
                            <flux:select.option :value="$supplier->id">{{ $supplier->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="supplierId" />
                </flux:field>

                <flux:field>
                    <flux:label>Store</flux:label>
                    <flux:select wire:model="storeId">
                        @foreach($stores as $store)
                            <flux:select.option :value="$store->id">{{ $store->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="storeId" />
                </flux:field>

                <flux:field>
                    <flux:label>Type</flux:label>
                    <flux:select wire:model="poType">
                        @foreach($poTypes as $typeKey => $typeMeta)
                            <flux:select.option :value="$typeKey">{{ $typeMeta['label'] ?? $typeKey }}</flux:select.option>
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

            <x-signals.card title="Line items">
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
                                        <flux:select wire:model.live="lines.{{ $index }}.product_id">
                                            <flux:select.option value="">—</flux:select.option>
                                            @foreach($products as $product)
                                                <flux:select.option :value="$product->id">{{ $product->name }}</flux:select.option>
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
            </x-signals.card>

            <div class="flex gap-2">
                <flux:button type="submit" variant="primary">Save draft</flux:button>
                <a href="{{ route('purchase-orders.index') }}" wire:navigate class="s-btn s-btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</section>
