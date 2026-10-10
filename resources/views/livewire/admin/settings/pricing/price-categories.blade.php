<?php

use App\Actions\Pricing\DeletePriceCategory;
use App\Models\PriceCategory;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Price Categories')] class extends Component {
    /** @var \Illuminate\Database\Eloquent\Collection<int, PriceCategory> */
    public $categories;

    public function mount(): void
    {
        $this->loadCategories();
    }

    public function loadCategories(): void
    {
        $this->categories = PriceCategory::query()->withCount('tiers')->orderBy('name')->get();
    }

    public function deleteCategory(int $categoryId): void
    {
        $category = PriceCategory::findOrFail($categoryId);

        (new DeletePriceCategory)($category);
        $this->loadCategories();
    }
}; ?>

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
                                    <a href="{{ route('admin.settings.pricing.price-categories.edit', $category) }}" wire:navigate.hover class="s-btn s-btn-ghost s-btn-sm" title="Edit">
                                        <flux:icon.pencil-square class="w-4 h-4" />
                                    </a>
                                    <button wire:click="deleteCategory({{ $category->id }})"
                                            wire:confirm="Delete this price category?"
                                            class="s-btn-ghost s-btn-xs text-[var(--red)]">
                                        Delete
                                    </button>
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
