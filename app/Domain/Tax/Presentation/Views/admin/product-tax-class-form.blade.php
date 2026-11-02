<section class="w-full">
    <x-admin.layout group="tax" :title="$isEditing ? 'Edit Product Tax Class' : 'Create Product Tax Class'" :description="$isEditing ? 'Update this product tax class.' : 'Add a new product tax classification.'">
        <x-slot:breadcrumbs>
            <x-signals.breadcrumb :items="[
                ['label' => 'Product Tax Classes', 'href' => route('admin.settings.tax.product-tax-classes')],
                ['label' => $isEditing ? 'Edit' : 'Create'],
            ]" />
        </x-slot:breadcrumbs>
        <x-slot:actions>
            <flux:button variant="ghost" href="{{ route('admin.settings.tax.product-tax-classes') }}" wire:navigate.hover>
                Back to Product Tax Classes
            </flux:button>
        </x-slot:actions>

        <form wire:submit="save" class="space-y-6">
            <x-signals.form-section title="Tax Class Details">
                <div class="space-y-4">
                    <flux:input wire:model="name" label="Name" required />
                    @error('name') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                    <flux:textarea wire:model="description" label="Description" rows="2" />
                    @error('description') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                    <flux:checkbox wire:model="isDefault" label="Default tax class" />
                </div>
            </x-signals.form-section>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">
                    {{ $isEditing ? 'Update Tax Class' : 'Create Tax Class' }}
                </flux:button>
                <flux:button variant="ghost" href="{{ route('admin.settings.tax.product-tax-classes') }}" wire:navigate.hover>
                    Cancel
                </flux:button>
            </div>
        </form>
    </x-admin.layout>
</section>
