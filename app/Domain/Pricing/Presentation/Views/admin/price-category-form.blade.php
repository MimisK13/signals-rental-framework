<section class="w-full">
    <x-admin.layout group="pricing" :title="$isEditing ? 'Edit Price Category' : 'Create Price Category'">
        <x-dense::card>
            <form wire:submit="save" class="space-y-6">
                <div class="space-y-4">
                    <flux:input wire:model="name" label="Name" required />
                    <flux:textarea wire:model="description" label="Description" rows="3" />
                    <flux:checkbox wire:model="isActive" label="Active" />
                </div>

                <x-signals.form-section title="Tier Multipliers">
                    <div class="space-y-3">
                        @foreach($tiers as $index => $tier)
                            <div class="grid grid-cols-4 gap-3 items-end max-sm:grid-cols-1" wire:key="tier-{{ $index }}">
                                <flux:input wire:model="tiers.{{ $index }}.tier_number" label="Tier #" type="number" min="1" required />
                                <flux:input wire:model="tiers.{{ $index }}.label" label="Label" />
                                <flux:input wire:model="tiers.{{ $index }}.multiplier" label="Multiplier" required />
                                <div class="flex gap-2">
                                    <flux:button type="button" wire:click="removeTier({{ $index }})" variant="ghost" class="text-[var(--red)]">
                                        Remove
                                    </flux:button>
                                </div>
                            </div>
                        @endforeach

                        <flux:button type="button" wire:click="addTier" variant="ghost">Add Tier</flux:button>
                    </div>
                </x-signals.form-section>

                <div class="flex items-center gap-3 pt-2">
                    <flux:button type="submit" variant="primary">Save</flux:button>
                    <flux:button href="{{ route('admin.settings.pricing.price-categories') }}" wire:navigate.hover variant="ghost">Cancel</flux:button>
                </div>
            </form>
        </x-dense::card>
    </x-admin.layout>
</section>
