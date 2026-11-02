<section class="w-full">
    <x-admin.layout group="tax" :title="$taxZoneId ? 'Edit Tax Zone' : 'Create Tax Zone'" :description="$taxZoneId ? 'Update zone metadata.' : 'Define a geographic tax zone.'">
        <x-slot:breadcrumbs>
            <x-signals.breadcrumb :items="[
                ['label' => 'Tax Zones', 'href' => route('admin.settings.tax.zones')],
                ['label' => $taxZoneId ? 'Edit' : 'Create'],
            ]" />
        </x-slot:breadcrumbs>

        <form wire:submit="save" class="space-y-6">
            <x-signals.form-section title="Zone Details">
                <div class="space-y-4">
                    <flux:input wire:model="name" label="Name" required />
                    <flux:input wire:model="slug" label="Slug" required />
                    <flux:textarea wire:model="description" label="Description" />
                    <flux:input wire:model="priority" label="Priority" type="number" min="0" />
                    <flux:checkbox wire:model="isActive" label="Active" />
                </div>
            </x-signals.form-section>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">{{ $taxZoneId ? 'Update' : 'Create' }}</flux:button>
                <flux:button variant="ghost" href="{{ route('admin.settings.tax.zones') }}" wire:navigate.hover>Cancel</flux:button>
            </div>
        </form>
    </x-admin.layout>
</section>
