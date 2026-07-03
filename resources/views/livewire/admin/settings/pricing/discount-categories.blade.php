<?php

use App\Actions\Pricing\DeleteDiscountCategory;
use App\Models\DiscountCategory;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Discount Categories')] class extends Component {
    /** @var \Illuminate\Database\Eloquent\Collection<int, DiscountCategory> */
    public $categories;

    public function mount(): void
    {
        $this->loadCategories();
    }

    public function loadCategories(): void
    {
        $this->categories = DiscountCategory::query()->orderBy('name')->get();
    }

    public function deleteCategory(int $categoryId): void
    {
        $category = DiscountCategory::findOrFail($categoryId);

        (new DeleteDiscountCategory)($category);
        $this->loadCategories();
    }
}; ?>

<section class="w-full">
    <x-admin.layout group="pricing" title="Discount Categories" description="Define discount categories assignable to organisations. Rules and auto-apply arrive in later pricing milestones.">
        <x-slot:actions>
            @can('pricing.manage')
                <flux:button variant="primary" href="{{ route('admin.settings.pricing.discount-categories.create') }}" wire:navigate>
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
                                    <a href="{{ route('admin.settings.pricing.discount-categories.edit', $category) }}" wire:navigate class="s-btn s-btn-ghost s-btn-sm" title="Edit">
                                        <flux:icon.pencil-square class="w-4 h-4" />
                                    </a>
                                    <button wire:click="deleteCategory({{ $category->id }})"
                                            wire:confirm="Delete this discount category?"
                                            class="s-btn-ghost s-btn-xs text-[var(--red)]">
                                        Delete
                                    </button>
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
