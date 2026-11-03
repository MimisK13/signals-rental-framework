<section class="w-full">
    @include('products::partials.product-header', ['product' => $product])

    @include('products::partials.product-tabs', ['product' => $product, 'activeTab' => 'container-config'])

    <div class="px-6 py-4 max-md:px-5 max-sm:px-3">
        <x-signals.panel title="Container Configuration">
            @unless($canManage)
                <flux:callout variant="warning" class="mb-4">
                    You can view container settings but need the Manage Container Templates permission to edit them.
                </flux:callout>
            @endunless

            <form wire:submit="save" class="space-y-6">
                <flux:switch wire:model.live="isContainerable" label="Containerable" :disabled="! $canManage" />

                @if($isContainerable)
                    <div class="grid grid-cols-2 gap-4 max-md:grid-cols-1">
                        <flux:select wire:model.live="availabilityMode" label="Availability Mode" :disabled="! $canManage">
                            @foreach($availabilityModes as $mode)
                                <option value="{{ $mode->value }}">{{ $mode->label() }}</option>
                            @endforeach
                        </flux:select>

                        @if($showCheckinMode)
                            <flux:select wire:model="checkinMode" label="Check-in Mode" :disabled="! $canManage">
                                <option value="">Default (parent)</option>
                                @foreach($checkinModes as $mode)
                                    <option value="{{ $mode->value }}">{{ $mode->label() }}</option>
                                @endforeach
                            </flux:select>
                        @endif

                        <flux:input wire:model.number="maxNestingDepth" type="number" min="1" max="3" label="Max Nesting Depth" :disabled="! $canManage" />
                        <flux:switch wire:model="repackOnReturn" label="Repack on Return" :disabled="! $canManage" />
                        <flux:switch wire:model="isWarehouseAddable" label="Warehouse Addable at Dispatch" :disabled="! $canManage" />
                        <flux:input wire:model.number="warehouseAddDefaultCharge" type="number" min="0" label="Warehouse Add Default Charge (minor units)" :disabled="! $canManage" />
                    </div>

                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-medium text-(--text-secondary)">Template Slots</h3>
                            @if($canManage)
                                <flux:button type="button" size="sm" wire:click="addTemplateSlot">Add Slot</flux:button>
                            @endif
                        </div>

                        @forelse($templateSlots as $index => $slot)
                            <div class="grid grid-cols-[1fr_120px_120px_auto] gap-3 items-end max-lg:grid-cols-1" wire:key="template-slot-{{ $index }}">
                                <div>
                                    @if($slot['product_id'])
                                        <flux:input label="Product" value="{{ $slot['product_name'] }}" readonly />
                                    @else
                                        <flux:input wire:model.live.debounce.300ms="productSearch" label="Product" placeholder="Search serialised products…" :disabled="! $canManage" />
                                        @if($productSearch !== '' && $productSearchResults->isNotEmpty())
                                            <ul class="mt-1 rounded border border-(--border-subtle) bg-(--surface-primary) shadow-sm">
                                                @foreach($productSearchResults as $result)
                                                    <li>
                                                        <button type="button" class="w-full px-3 py-2 text-left text-sm hover:bg-(--surface-secondary)" wire:click="selectSlotProduct({{ $index }}, {{ $result->id }}, @js($result->name))">
                                                            {{ $result->name }}
                                                        </button>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    @endif
                                </div>
                                <flux:input wire:model.number="templateSlots.{{ $index }}.quantity" type="number" min="1" label="Qty" :disabled="! $canManage" />
                                @if($isHybrid)
                                    <flux:select wire:model="templateSlots.{{ $index }}.binding" label="Binding" :disabled="! $canManage">
                                        @foreach($bindings as $binding)
                                            <option value="{{ $binding->value }}">{{ $binding->label() }}</option>
                                        @endforeach
                                    </flux:select>
                                @else
                                    <flux:input label="Binding" value="{{ ucfirst($slot['binding']) }}" readonly />
                                @endif
                                @if($canManage)
                                    <flux:button type="button" variant="danger" size="sm" wire:click="removeTemplateSlot({{ $index }})">Remove</flux:button>
                                @endif
                            </div>
                        @empty
                            <p class="text-sm text-(--text-muted)">No template slots defined. Add slots to define the strict packing specification.</p>
                        @endforelse
                    </div>
                @endif

                @if($canManage)
                    <div class="flex justify-end">
                        <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="save">Save Container Config</span>
                            <span wire:loading wire:target="save">Saving…</span>
                        </flux:button>
                    </div>
                @endif
            </form>
        </x-signals.panel>
    </div>
</section>
