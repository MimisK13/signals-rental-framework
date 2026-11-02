<section class="w-full">
    <x-admin.layout group="tax" :title="$isEditing ? 'Edit Tax Rate' : 'Create Tax Rate'" :description="$isEditing ? 'Update this tax rate.' : 'Add a new tax rate.'">
        <x-slot:breadcrumbs>
            <x-signals.breadcrumb :items="[
                ['label' => 'Tax Rates', 'href' => route('admin.settings.tax.rates')],
                ['label' => $isEditing ? 'Edit' : 'Create'],
            ]" />
        </x-slot:breadcrumbs>
        <x-slot:actions>
            <flux:button variant="ghost" href="{{ route('admin.settings.tax.rates') }}" wire:navigate.hover>
                Back to Tax Rates
            </flux:button>
        </x-slot:actions>

        <form wire:submit="save" class="space-y-6">
            <x-signals.form-section title="Tax Rate Details">
                <div class="space-y-4">
                    <flux:input wire:model="name" label="Name" required />
                    @error('name') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                    <flux:textarea wire:model="description" label="Description" rows="2" />
                    @error('description') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                    <flux:input wire:model="rate" label="Rate (%)" type="number" step="0.01" min="0" max="100" required />
                    @error('rate') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                    <flux:checkbox wire:model="isActive" label="Active" />
                </div>
            </x-signals.form-section>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">
                    {{ $isEditing ? 'Update Tax Rate' : 'Create Tax Rate' }}
                </flux:button>
                <flux:button variant="ghost" href="{{ route('admin.settings.tax.rates') }}" wire:navigate.hover>
                    Cancel
                </flux:button>
            </div>
        </form>
    </x-admin.layout>
</section>
