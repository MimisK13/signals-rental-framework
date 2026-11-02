<section class="w-full">
    <x-admin.layout group="tax" :title="$isEditing ? 'Edit Zone Tax Rule' : 'Create Zone Tax Rule'" :description="$isEditing ? 'Update cross-border tax treatment matching.' : 'Match seller and buyer zones to a tax result type.'">
        <x-slot:breadcrumbs>
            <x-signals.breadcrumb :items="[
                ['label' => 'Tax Rules', 'href' => route('admin.settings.tax.rules')],
                ['label' => $isEditing ? 'Edit Zone Rule' : 'Create Zone Rule'],
            ]" />
        </x-slot:breadcrumbs>

        <form wire:submit="save" class="space-y-6">
            <x-signals.form-section title="Zone Matching">
                <div class="space-y-4">
                    <flux:input wire:model="name" label="Name" required />
                    <flux:textarea wire:model="description" label="Description" />
                    <flux:select wire:model="sellerZoneId" label="Seller zone" required>
                        <flux:select.option value="">Select...</flux:select.option>
                        @foreach($taxZones as $zone)
                            <flux:select.option value="{{ $zone->id }}">{{ $zone->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model="buyerZoneId" label="Buyer zone" required>
                        <flux:select.option value="">Select...</flux:select.option>
                        @foreach($taxZones as $zone)
                            <flux:select.option value="{{ $zone->id }}">{{ $zone->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model="buyerType" label="Buyer type (optional)">
                        <flux:select.option value="">Any</flux:select.option>
                        @foreach($buyerTypes as $type)
                            <flux:select.option value="{{ $type->value }}">{{ $type->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model="hasTaxId" label="Requires validated tax ID">
                        <flux:select.option :value="null">Any</flux:select.option>
                        <flux:select.option :value="true">Yes</flux:select.option>
                        <flux:select.option :value="false">No</flux:select.option>
                    </flux:select>
                    <flux:select wire:model="taxCategoryId" label="Tax category (optional)">
                        <flux:select.option value="">Any</flux:select.option>
                        @foreach($taxCategories as $category)
                            <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model="resultType" label="Result type" required>
                        @foreach($resultTypes as $type)
                            <flux:select.option value="{{ $type->value }}">{{ $type->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:textarea wire:model="legalText" label="Legal text" />
                    <flux:input wire:model="priority" label="Priority" type="number" min="0" />
                    <flux:checkbox wire:model="isActive" label="Active" />
                </div>
            </x-signals.form-section>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">{{ $isEditing ? 'Update' : 'Create' }}</flux:button>
                <flux:button variant="ghost" href="{{ route('admin.settings.tax.rules') }}" wire:navigate.hover>Cancel</flux:button>
            </div>
        </form>
    </x-admin.layout>
</section>
