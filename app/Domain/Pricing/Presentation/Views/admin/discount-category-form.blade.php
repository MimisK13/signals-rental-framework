<section class="w-full">
    <x-admin.layout group="pricing" :title="$isEditing ? 'Edit Discount Category' : 'Create Discount Category'">
        <x-dense::card>
            <form wire:submit="save" class="space-y-6">
                <div class="space-y-4">
                    <flux:input wire:model="name" label="Name" required />
                    <flux:textarea wire:model="description" label="Description" rows="3" />
                    <flux:checkbox wire:model="isActive" label="Active" />
                </div>

                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <flux:heading size="sm">Discount Rules</flux:heading>
                        <flux:button type="button" wire:click="addRule" variant="ghost" size="sm">Add Rule</flux:button>
                    </div>

                    <p class="text-sm text-[var(--text-muted)]">
                        Rules are definitions only in this milestone — matching against opportunity lines arrives in a later phase.
                        Empty criteria match all products; multiple criteria combine with AND logic; tag criteria require every listed tag.
                    </p>

                    @forelse($rules as $index => $rule)
                        <div wire:key="discount-rule-{{ $index }}" class="rounded-lg border border-[var(--border)] p-4 space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="font-medium">Rule {{ $index + 1 }}</span>
                                <button type="button" wire:click="removeRule({{ $index }})" class="s-btn-ghost s-btn-xs text-[var(--red)]">Remove</button>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <flux:input wire:model="rules.{{ $index }}.discount_percent" label="Discount %" type="number" step="0.01" min="0" max="100" required />
                                <flux:select wire:model="rules.{{ $index }}.rule_scope" label="Quantity scope">
                                    @foreach($ruleScopeOptions as $option)
                                        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                    @endforeach
                                </flux:select>
                                <flux:input wire:model="rules.{{ $index }}.quantity_min" label="Min quantity" type="number" min="0" />
                                <flux:input wire:model="rules.{{ $index }}.quantity_max" label="Max quantity" type="number" min="0" />
                                <flux:input wire:model="rules.{{ $index }}.sort_order" label="Sort order" type="number" min="0" />
                                <flux:input wire:model="rules.{{ $index }}.valid_from" label="Valid from" type="datetime-local" />
                                <flux:input wire:model="rules.{{ $index }}.valid_to" label="Valid to" type="datetime-local" />
                            </div>

                            <flux:select wire:model="rules.{{ $index }}.product_group_ids" label="Product groups" multiple>
                                @foreach($productGroups as $group)
                                    <option value="{{ $group->id }}">{{ $group->name }}</option>
                                @endforeach
                            </flux:select>

                            <flux:input wire:model="rules.{{ $index }}.tag_names" label="Tags (comma-separated, all required)" placeholder="lighting, led" />

                            <flux:select wire:model="rules.{{ $index }}.product_ids" label="Products" multiple>
                                @foreach($products as $product)
                                    <option value="{{ $product->id }}">{{ $product->name }}</option>
                                @endforeach
                            </flux:select>

                            <flux:select wire:model="rules.{{ $index }}.transaction_types" label="Transaction types" multiple>
                                @foreach($transactionTypeOptions as $option)
                                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                @endforeach
                            </flux:select>
                        </div>
                    @empty
                        <p class="text-sm text-[var(--text-muted)]">No rules yet. Add a blanket rule or criteria-specific discounts.</p>
                    @endforelse
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <flux:button type="submit" variant="primary">Save</flux:button>
                    <flux:button href="{{ route('admin.settings.pricing.discount-categories') }}" wire:navigate.hover variant="ghost">Cancel</flux:button>
                </div>
            </form>
        </x-dense::card>
    </x-admin.layout>
</section>
