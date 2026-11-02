<section class="w-full">
    <x-dense::page-header title="Repack on return">
        <x-slot:meta>
            <a href="{{ route('opportunities.assets', $opportunity) }}" wire:navigate.hover class="text-[var(--link)] hover:underline">{{ $opportunity->subject }}</a>
            ·
            <a href="{{ route('containers.show', $container) }}" wire:navigate.hover class="text-[var(--link)] hover:underline">{{ $container->name }}</a>
        </x-slot:meta>
        <x-slot:actions>
            @if($canSeal)
                <flux:button wire:click="seal" wire:loading.attr="disabled" variant="primary">Seal container</flux:button>
            @endif
        </x-slot:actions>
    </x-dense::page-header>

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3 space-y-4">
        <x-dense::card>
            <form wire:submit="scanReturnItem" class="flex flex-wrap items-end gap-3">
                <flux:field class="min-w-[16rem] flex-1">
                    <flux:label>Scan return item</flux:label>
                    <flux:input wire:model="scanInput" class="font-mono" placeholder="Asset number, serial, or ID…" autofocus autocomplete="off" />
                </flux:field>
                <flux:checkbox wire:model="freeScan" label="Free scan (pack unexpected items)" />
                <flux:button type="submit" wire:loading.attr="disabled" variant="primary">Return &amp; repack</flux:button>
            </form>
            @error('scanInput')
                <p class="mt-2 text-sm text-[var(--danger)]">{{ $message }}</p>
            @enderror
        </x-dense::card>

        <div class="grid gap-4 lg:grid-cols-2">
            <x-dense::card>
                <h2 class="text-sm font-semibold mb-3">Outstanding on opportunity</h2>
                <ul class="space-y-2 text-sm">
                    @forelse($outstanding as $asset)
                        <li wire:key="outstanding-{{ $asset->id }}" class="flex items-center justify-between gap-2 rounded-md border border-[var(--border)] px-3 py-2">
                            <span>{{ $asset->stockLevel?->product?->name ?? $asset->item?->name }}</span>
                            <span style="font-family: var(--font-mono);" class="text-[var(--muted)]">{{ $asset->stockLevel?->asset_number ?? $asset->stock_level_id }}</span>
                        </li>
                    @empty
                        <li class="text-[var(--muted)] py-4 text-center">All items returned.</li>
                    @endforelse
                </ul>
            </x-dense::card>

            <x-dense::card>
                <h2 class="text-sm font-semibold mb-3">Rebuilt container</h2>
                @if($isStrict)
                    <div class="grid gap-2">
                        @foreach($compliance->slots as $slot)
                            <div wire:key="repack-slot-{{ $slot->product_id }}" class="flex items-center justify-between rounded-md border border-[var(--border)] px-3 py-2">
                                <div>
                                    <div class="font-medium">{{ $productNames[$slot->product_id] ?? 'Product #'.$slot->product_id }}</div>
                                    <div class="text-xs text-[var(--muted)]">Required × {{ $slot->expected_quantity }}</div>
                                </div>
                                <span class="s-badge {{ match($slot->status) {
                                    'ok' => 's-badge-green',
                                    'under' => 's-badge-amber',
                                    'over' => 's-badge-red',
                                    default => 's-badge-zinc',
                                } }}">
                                    {{ $slot->actual_quantity }} / {{ $slot->expected_quantity }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
                <ul class="mt-3 space-y-1 text-sm">
                    @forelse($container->activeItems as $item)
                        <li wire:key="repacked-{{ $item->id }}">
                            {{ $item->product?->name }} —
                            <span style="font-family: var(--font-mono);">{{ $item->serialisedItem?->asset_number ?? $item->serialised_item_id }}</span>
                        </li>
                    @empty
                        <li class="text-[var(--muted)]">Scan items to rebuild the container.</li>
                    @endforelse
                </ul>
            </x-dense::card>
        </div>
    </div>
</section>
