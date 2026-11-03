<section class="w-full">
    <x-dense::page-header title="New Virtual Stock Intake">
        <x-slot:breadcrumbs>
            <a href="{{ route('virtual-stock.index') }}" wire:navigate.hover class="text-[var(--link)] hover:underline">Virtual Stock</a>
        </x-slot:breadcrumbs>
    </x-dense::page-header>

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
    <form wire:submit="save" class="max-w-2xl space-y-4">
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
    </div>
</section>
