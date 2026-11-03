<section class="w-full">
    @include('products::partials.product-header', [
        'product' => $product,
        'subpage' => $isEditing ? 'Edit Rate' : 'Assign Rate',
    ])
    @include('products::partials.product-tabs', ['product' => $product, 'activeTab' => 'rates'])

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        <form wire:submit="save" class="max-w-2xl space-y-8">
            <x-signals.form-section title="Rate Assignment"
                description="Choose the rate definition and unit price the engine operates on. Priority breaks ties when rates overlap — higher wins.">
                <div class="space-y-4">
                    <flux:select wire:model="rateDefinitionId" label="Rate Definition" required>
                        <flux:select.option value="">Select a rate definition…</flux:select.option>
                        @foreach ($rateDefinitions as $definition)
                            <flux:select.option value="{{ $definition->id }}">{{ $definition->name }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>

                    <div class="grid grid-cols-2 gap-4">
                        <flux:select wire:model="transactionType" label="Transaction Type">
                            @foreach ($transactionTypes as $type)
                                <flux:select.option value="{{ $type->value }}">{{ $type->label() }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:input wire:model="priority" type="number" label="Priority" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <flux:input wire:model="price" type="text" inputmode="decimal" label="Unit Price"
                            placeholder="0.00" required />
                        <flux:select wire:model="currency" label="Currency">
                            @foreach ($currencies as $c)
                                <flux:select.option value="{{ $c->code }}">{{ $c->code }} —
                                    {{ $c->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                </div>
            </x-signals.form-section>

            <x-signals.form-section title="Scope"
                description="Optionally limit this rate to a store, price category, and a validity window.">
                <div class="space-y-4">
                    <flux:select wire:model="storeId" label="Store">
                        <flux:select.option value="">All stores</flux:select.option>
                        @foreach ($stores as $store)
                            <flux:select.option value="{{ $store->id }}">{{ $store->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="priceCategoryId" label="Price Category">
                        <flux:select.option value="">Default (all categories)</flux:select.option>
                        @foreach ($priceCategories as $category)
                            <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <div class="grid grid-cols-2 gap-4">
                        <flux:input wire:model="validFrom" type="date" label="Valid From" />
                        <flux:input wire:model="validTo" type="date" label="Valid To" />
                    </div>
                </div>
            </x-signals.form-section>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">{{ $isEditing ? 'Save Rate' : 'Assign Rate' }}
                </flux:button>
                <flux:button variant="ghost" href="{{ route('products.rates', $product) }}" wire:navigate.hover>Cancel
                </flux:button>
            </div>
        </form>
    </div>
</section>
