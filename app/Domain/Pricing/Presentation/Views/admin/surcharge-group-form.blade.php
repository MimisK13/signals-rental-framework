<section class="w-full">
    <x-admin.layout group="pricing" :title="$isEditing ? 'Edit Surcharge Group' : 'Create Surcharge Group'">
        <x-dense::card>
            <form wire:submit="save" class="space-y-6">
                <div class="space-y-4">
                    <flux:input wire:model="name" label="Name" required />
                    <flux:textarea wire:model="description" label="Description" rows="3" />
                    <flux:checkbox wire:model="isDefault" label="Default group (auto-enabled on new opportunities)" />
                    <flux:checkbox wire:model="isActive" label="Active" />
                </div>

                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <flux:heading size="sm">Surcharges</flux:heading>
                        <flux:button type="button" wire:click="addSurcharge" variant="ghost" size="sm">Add Surcharge</flux:button>
                    </div>

                    @forelse($surcharges as $index => $surcharge)
                        <div wire:key="surcharge-{{ $index }}" class="rounded-lg border border-[var(--border)] p-4 space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="font-medium">Surcharge {{ $index + 1 }}</span>
                                <flux:button type="button" wire:click="removeSurcharge({{ $index }})" variant="ghost" size="sm">Remove</flux:button>
                            </div>
                            <flux:input wire:model="surcharges.{{ $index }}.name" label="Name" required />
                            <div class="grid gap-4 md:grid-cols-2">
                                <flux:input wire:model="surcharges.{{ $index }}.percent_value" label="Percent value (%)" />
                                <flux:input wire:model="surcharges.{{ $index }}.fixed_value_minor" label="Fixed value (minor units)" />
                            </div>
                            <flux:select wire:model="surcharges.{{ $index }}.product_tax_class_id" label="Tax class">
                                <option value="">Inherit from product / default</option>
                                @foreach($productTaxClasses as $taxClass)
                                    <option value="{{ $taxClass->id }}">{{ $taxClass->name }}</option>
                                @endforeach
                            </flux:select>
                            <flux:checkbox wire:model="surcharges.{{ $index }}.is_default" label="Default (applies when group is active)" />
                        </div>
                    @empty
                        <p class="text-sm text-[var(--text-muted)]">No surcharges yet.</p>
                    @endforelse
                </div>

                <div class="flex justify-end gap-2">
                    <flux:button href="{{ route('admin.settings.pricing.surcharge-groups') }}" wire:navigate.hover variant="ghost">Cancel</flux:button>
                    <flux:button type="submit" variant="primary">Save</flux:button>
                </div>
            </form>
        </x-dense::card>
    </x-admin.layout>
</section>
