<section class="w-full">
    <x-admin.layout group="tax" :title="$taxCategoryId ? 'Edit Tax Category' : 'Create Tax Category'" :description="$taxCategoryId ? 'Update category metadata.' : 'Define a product tax category.'">
        <x-slot:breadcrumbs>
            <x-signals.breadcrumb :items="[
                ['label' => 'Tax Categories', 'href' => route('admin.settings.tax.categories')],
                ['label' => $taxCategoryId ? 'Edit' : 'Create'],
            ]" />
        </x-slot:breadcrumbs>

        <form wire:submit="save" class="space-y-6">
            <x-signals.form-section title="Category Details">
                <div class="space-y-4">
                    <flux:input wire:model="name" label="Name" required />
                    <flux:input wire:model="slug" label="Slug" required />
                    <flux:select wire:model="type" label="Type" required>
                        @foreach($types as $categoryType)
                            <flux:select.option value="{{ $categoryType->value }}">{{ $categoryType->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:textarea wire:model="description" label="Description" />
                    <flux:checkbox wire:model="isDefault" label="Default category" />
                    <flux:checkbox wire:model="isActive" label="Active" />
                </div>
            </x-signals.form-section>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">{{ $taxCategoryId ? 'Update' : 'Create' }}</flux:button>
                <flux:button variant="ghost" href="{{ route('admin.settings.tax.categories') }}" wire:navigate.hover>Cancel</flux:button>
            </div>
        </form>
    </x-admin.layout>
</section>
