<section class="w-full max-w-3xl">
    <x-dense::page-header :title="$repair?->exists ? 'Edit Repair' : 'New Repair'">
        <x-slot:breadcrumbs><a href="{{ route('repairs.index') }}" wire:navigate.hover class="text-[var(--link)] hover:underline">Repairs</a></x-slot:breadcrumbs>
    </x-dense::page-header>

    <form wire:submit="save" class="space-y-4 px-6 py-4 max-md:px-5 max-sm:px-3">
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:select wire:model="product_id" label="Product" required>
                <option value="">Select product</option>
                @foreach($products as $product)<option value="{{ $product->id }}">{{ $product->name }}</option>@endforeach
            </flux:select>
            <flux:select wire:model="store_id" label="Store" required>
                <option value="">Select store</option>
                @foreach($stores as $store)<option value="{{ $store->id }}">{{ $store->name }}</option>@endforeach
            </flux:select>
        </div>
        <flux:select wire:model="asset_id" label="Serialized Asset (optional)">
            <option value="">Bulk repair</option>
            @foreach($assets as $asset)<option value="{{ $asset->id }}">{{ $asset->product?->name }} — {{ $asset->currentStockLevel?->asset_number ?? 'Asset #'.$asset->id }}</option>@endforeach
        </flux:select>
        <flux:input wire:model="quantity" type="number" min="1" label="Quantity" required />
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:select wire:model="status" label="Status" required>
                @foreach($statuses as $statusOption)<option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</option>@endforeach
            </flux:select>
            <flux:checkbox wire:model="is_usable" label="Equipment remains usable" />
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input wire:model="reported_at" type="datetime-local" label="Reported at" required />
            <flux:input wire:model="started_at" type="datetime-local" label="Started at" />
            <flux:input wire:model="expected_completed_at" type="datetime-local" label="Expected completion" />
            <flux:select wire:model="external_repairer_id" label="External repairer (optional)">
                <option value="">None</option>
                @foreach($members as $member)<option value="{{ $member->id }}">{{ $member->name }}</option>@endforeach
            </flux:select>
        </div>
        <flux:textarea wire:model="description" label="Description" rows="3" />
        <flux:textarea wire:model="notes" label="Notes" rows="3" />
        <div class="flex gap-2"><flux:button type="submit" variant="primary">Save repair</flux:button><a href="{{ route('repairs.index') }}" wire:navigate.hover class="s-btn s-btn-secondary">Cancel</a></div>
    </form>
</section>
