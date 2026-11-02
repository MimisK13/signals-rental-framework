<section class="w-full">
    <x-admin.layout group="data" :title="$isEditing ? 'Edit Custom Field Group' : 'Create Custom Field Group'" :description="$isEditing ? 'Update group details.' : 'Create a new custom field group.'">
        <x-slot:breadcrumbs>
            <x-signals.breadcrumb :items="[
                ['label' => 'Custom Field Groups', 'href' => route('admin.settings.custom-field-groups')],
                ['label' => $isEditing ? 'Edit' : 'Create'],
            ]" />
        </x-slot:breadcrumbs>
        <x-slot:actions>
            <flux:button variant="ghost" href="{{ route('admin.settings.custom-field-groups') }}" wire:navigate.hover>
                Back to Custom Field Groups
            </flux:button>
        </x-slot:actions>

        <form wire:submit="save" class="space-y-8">
            <x-signals.form-section title="Group Details">
                <div class="space-y-4">
                    <flux:input wire:model="name" label="Name" required />
                    <flux:textarea wire:model="description" label="Description" rows="3" />
                    <flux:input wire:model="sortOrder" label="Sort Order" type="number" min="0" />
                </div>
            </x-signals.form-section>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">{{ $isEditing ? 'Save Changes' : 'Create Group' }}</flux:button>
                <flux:button variant="ghost" href="{{ route('admin.settings.custom-field-groups') }}" wire:navigate.hover>Cancel</flux:button>
            </div>
        </form>
    </x-admin.layout>
</section>
