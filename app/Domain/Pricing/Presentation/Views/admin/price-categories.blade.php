<section class="w-full">
    <x-admin.layout group="pricing" title="Price Categories" description="Define alternative base price lists and tier multipliers. Product assignment and pipeline effects arrive in later milestones.">
        <x-slot:actions>
            @can('pricing.manage')
                <flux:button variant="primary" href="{{ route('admin.settings.pricing.price-categories.create') }}" wire:navigate.hover>
                    Add Price Category
                </flux:button>
            @endcan
        </x-slot:actions>

        <div class="s-table-wrap">
            <table class="s-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Tiers</th>
                        <th>Status</th>
                        <th class="w-[120px]"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($categories as $category)
                        <tr wire:key="price-category-{{ $category->id }}">
                            <td class="font-medium">{{ $category->name }}</td>
                            <td>{{ $category->description }}</td>
                            <td>{{ $category->tiers_count }}</td>
                            <td>
                                @if($category->is_active)
                                    <span class="s-badge s-badge-green">Active</span>
                                @else
                                    <span class="s-badge">Inactive</span>
                                @endif
                            </td>
                            <td class="text-right">
                                @can('pricing.manage')
                                    @include('pricing::partials.row-actions', [
                                        'editUrl' => route('admin.settings.pricing.price-categories.edit', $category),
                                        'deleteMethod' => 'deleteCategory',
                                        'recordId' => $category->id,
                                        'confirmation' => 'Delete this price category?',
                                    ])
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-[var(--text-muted)]">No price categories configured.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.layout>
</section>
