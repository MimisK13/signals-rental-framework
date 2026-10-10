<?php

use App\Actions\VirtualStock\CreateVirtualStockIntake;
use App\Data\VirtualStock\CreateVirtualStockIntakeData;
use App\Models\Product;
use App\Models\Store;
use App\Models\VirtualStockIntake;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public ?int $purchase_order_id = null;

    public ?int $purchase_order_item_id = null;

    public ?int $product_id = null;

    public ?int $store_id = null;

    public int $quantity = 1;

    public string $available_from = '';

    public string $available_until = '';

    public int $unit_cost = 0;

    public ?string $notes = null;

    public function mount(): void
    {
        Gate::authorize('create', VirtualStockIntake::class);

        $this->purchase_order_id = request()->integer('purchase_order_id') ?: null;
        $this->purchase_order_item_id = request()->integer('purchase_order_item_id') ?: null;
    }

    public function save(): void
    {
        $result = (new CreateVirtualStockIntake)(CreateVirtualStockIntakeData::from([
            'product_id' => (int) $this->product_id,
            'store_id' => (int) $this->store_id,
            'quantity' => $this->quantity,
            'available_from' => $this->available_from,
            'available_until' => $this->available_until,
            'unit_cost' => $this->unit_cost,
            'purchase_order_id' => $this->purchase_order_id,
            'purchase_order_item_id' => $this->purchase_order_item_id,
            'notes' => $this->notes,
        ]));

        $this->redirect(route('virtual-stock.show', $result->id), navigate: true);
    }

    public function with(): array
    {
        return [
            'products' => Product::query()->orderBy('name')->limit(100)->get(['id', 'name']),
            'stores' => Store::query()->orderBy('name')->get(['id', 'name']),
        ];
    }
}; ?>

<section class="w-full max-w-2xl">
    <x-signals.page-header title="New Virtual Stock Intake">
        <x-slot:breadcrumbs>
            <a href="{{ route('virtual-stock.index') }}" wire:navigate.hover class="text-[var(--link)] hover:underline">Virtual Stock</a>
        </x-slot:breadcrumbs>
    </x-signals.page-header>

    <form wire:submit="save" class="flex-1 px-6 py-4 space-y-4">
        <flux:select wire:model="product_id" label="Product" required>
            <option value="">Select product</option>
            @foreach($products as $product)
                <option value="{{ $product->id }}">{{ $product->name }}</option>
            @endforeach
        </flux:select>
        <flux:select wire:model="store_id" label="Store" required>
            <option value="">Select store</option>
            @foreach($stores as $store)
                <option value="{{ $store->id }}">{{ $store->name }}</option>
            @endforeach
        </flux:select>
        <flux:input wire:model="quantity" type="number" min="1" label="Quantity" required />
        <flux:input wire:model="available_from" type="datetime-local" label="Available from" required />
        <flux:input wire:model="available_until" type="datetime-local" label="Available until" required />
        <flux:input wire:model="unit_cost" type="number" min="0" label="Unit cost (minor units)" required />
        <flux:textarea wire:model="notes" label="Notes" rows="3" />
        <div class="flex gap-2">
            <flux:button type="submit" variant="primary">Create draft</flux:button>
            <a href="{{ route('virtual-stock.index') }}" wire:navigate.hover class="s-btn s-btn-secondary">Cancel</a>
        </div>
    </form>
</section>
