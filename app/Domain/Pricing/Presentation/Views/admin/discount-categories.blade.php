<section class="w-full">
    <x-admin.layout group="pricing" title="Discount Categories" description="Define discount categories assignable to organisations. Rules and auto-apply arrive in later pricing milestones.">
        <x-slot:actions>
            @can('pricing.manage')
                <flux:button variant="primary" href="{{ route('admin.settings.pricing.discount-categories.create') }}" wire:navigate.hover>
                    Add Discount Category
                </flux:button>
            @endcan
        </x-slot:actions>

        <div class="s-table-wrap">
            <table class="s-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th class="w-[120px]"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($categories as $category)
                        <tr wire:key="discount-category-{{ $category->id }}">
                            <td class="font-medium">{{ $category->name }}</td>
                            <td>{{ $category->description }}</td>
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
                                        'editUrl' => route('admin.settings.pricing.discount-categories.edit', $category),
                                        'deleteMethod' => 'deleteCategory',
                                        'recordId' => $category->id,
                                        'confirmation' => 'Delete this discount category?',
                                    ])
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-[var(--text-muted)]">No discount categories configured.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.layout>
</section>
