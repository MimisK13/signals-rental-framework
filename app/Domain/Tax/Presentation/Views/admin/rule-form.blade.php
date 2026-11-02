<section class="w-full">
    <x-admin.layout group="tax" :title="$isEditing ? 'Edit Tax Rule' : 'Create Tax Rule'" :description="$isEditing ? 'Update this tax rule.' : 'Map a tax rate to an organisation and product tax class combination.'">
        <x-slot:breadcrumbs>
            <x-signals.breadcrumb :items="[
                ['label' => 'Tax Rules', 'href' => route('admin.settings.tax.rules')],
                ['label' => $isEditing ? 'Edit' : 'Create'],
            ]" />
        </x-slot:breadcrumbs>
        <x-slot:actions>
            <flux:button variant="ghost" href="{{ route('admin.settings.tax.rules') }}" wire:navigate.hover>
                Back to Tax Rules
            </flux:button>
        </x-slot:actions>

        <form wire:submit="save" class="space-y-6">
            <x-signals.form-section title="Tax Rule Details">
                <div class="space-y-4">
                    <flux:select wire:model="organisationTaxClassId" label="Organisation Tax Class" required>
                        <flux:select.option value="">Select...</flux:select.option>
                        @foreach($organisationTaxClasses as $orgClass)
                            <flux:select.option value="{{ $orgClass->id }}">{{ $orgClass->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    @error('organisation_tax_class_id') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                    <flux:select wire:model="productTaxClassId" label="Product Tax Class" required>
                        <flux:select.option value="">Select...</flux:select.option>
                        @foreach($productTaxClasses as $prodClass)
                            <flux:select.option value="{{ $prodClass->id }}">{{ $prodClass->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    @error('product_tax_class_id') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                    <flux:select wire:model="taxRateId" label="Tax Rate" required>
                        <flux:select.option value="">Select...</flux:select.option>
                        @foreach($taxRates as $rate)
                            <flux:select.option value="{{ $rate->id }}">{{ $rate->name }} ({{ number_format($rate->rate, 2) }}%)</flux:select.option>
                        @endforeach
                    </flux:select>
                    @error('tax_rate_id') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                    <flux:input wire:model="priority" label="Priority" type="number" min="0" />
                    @error('priority') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                    <flux:checkbox wire:model="isActive" label="Active" />
                </div>
            </x-signals.form-section>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">
                    {{ $isEditing ? 'Update Tax Rule' : 'Create Tax Rule' }}
                </flux:button>
                <flux:button variant="ghost" href="{{ route('admin.settings.tax.rules') }}" wire:navigate.hover>
                    Cancel
                </flux:button>
            </div>
        </form>
    </x-admin.layout>
</section>
