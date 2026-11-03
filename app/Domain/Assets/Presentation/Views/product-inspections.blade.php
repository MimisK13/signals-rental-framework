<section class="w-full">
    @include('products::partials.product-header', ['product' => $product, 'subpage' => 'Inspections'])
    @include('products::partials.product-tabs', ['product' => $product, 'activeTab' => 'inspections'])

    <div class="space-y-6 px-6 py-4 max-md:px-5 max-sm:px-3">
        <x-signals.panel title="Compliance Summary">
            <div class="grid grid-cols-5 gap-3 max-lg:grid-cols-3 max-sm:grid-cols-2">
                @foreach([
                    'valid' => 'Valid',
                    'due_soon' => 'Due Soon',
                    'overdue' => 'Overdue',
                    'failed' => 'Failed',
                    'unknown' => 'Unknown',
                ] as $key => $label)
                    <div class="rounded-lg border border-[var(--card-border)] px-4 py-3">
                        <div class="text-2xl font-bold text-[var(--text-primary)]">{{ $summary[$key] ?? 0 }}</div>
                        <div class="mt-1 text-xs font-medium uppercase tracking-wide text-[var(--text-muted)]">{{ $label }}</div>
                    </div>
                @endforeach
            </div>
        </x-signals.panel>

        <x-signals.panel title="Inspection Policies">
            <form wire:submit="savePolicy" class="grid gap-4 md:grid-cols-4">
                <flux:input wire:model="name" label="Name" placeholder="Annual lifting inspection" />
                <flux:input wire:model="intervalMonths" type="number" min="1" label="Interval (months)" />
                <flux:input wire:model="description" label="Description" />
                <div class="flex items-end gap-2">
                    <flux:button type="submit" variant="primary">{{ $editingPolicyId ? 'Save policy' : 'Add policy' }}</flux:button>
                    @if($editingPolicyId)
                        <flux:button type="button" wire:click="resetPolicyForm">Cancel</flux:button>
                    @endif
                </div>
            </form>

            <div class="mt-6 s-table-wrap">
                <table class="s-table w-full">
                    <thead><tr><th>Name</th><th>Interval</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @forelse($policies as $policy)
                            <tr wire:key="inspection-policy-{{ $policy->id }}">
                                <td>{{ $policy->name }}</td>
                                <td>{{ $policy->interval_months }} months</td>
                                <td>{{ $policy->is_active ? 'Active' : 'Inactive' }}</td>
                                <td class="flex gap-2">
                                    <flux:button size="sm" wire:click="editPolicy({{ $policy->id }})">Edit</flux:button>
                                    @if($policy->is_active)
                                        <flux:button size="sm" wire:click="deactivatePolicy({{ $policy->id }})">Deactivate</flux:button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-8 text-center text-[var(--text-muted)]">No inspection policies recorded.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-signals.panel>
    </div>
</section>
