<?php

use App\Actions\Tax\DeleteTaxCategory;
use App\Models\TaxCategory;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Tax Categories')] class extends Component {
    /** @var \Illuminate\Database\Eloquent\Collection<int, TaxCategory> */
    public $categories;

    public function mount(): void
    {
        $this->loadCategories();
    }

    public function loadCategories(): void
    {
        $this->categories = TaxCategory::query()->orderBy('name')->get();
    }

    public function deleteCategory(int $categoryId): void
    {
        $category = TaxCategory::findOrFail($categoryId);
        (new DeleteTaxCategory)($category);
        $this->loadCategories();
    }
}; ?>

<section class="w-full">
    <x-admin.layout group="tax" title="Tax Categories" description="Product tax treatment classes (standard, reduced, zero, exempt).">
        <x-slot:actions>
            <flux:button variant="primary" href="{{ route('admin.settings.tax.categories.create') }}" wire:navigate>
                Add Tax Category
            </flux:button>
        </x-slot:actions>

        <div class="s-table-wrap">
            <table class="s-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Type</th>
                        <th>Default</th>
                        <th>Status</th>
                        <th class="w-[120px]"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($categories as $category)
                        <tr wire:key="tax-category-{{ $category->id }}">
                            <td class="font-medium">{{ $category->name }}</td>
                            <td><code>{{ $category->slug }}</code></td>
                            <td>{{ $category->type->label() }}</td>
                            <td>{{ $category->is_default ? 'Yes' : 'No' }}</td>
                            <td>
                                @if($category->is_active)
                                    <span class="s-badge s-badge-green">Active</span>
                                @else
                                    <span class="s-badge s-badge-zinc">Inactive</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <a href="{{ route('admin.settings.tax.categories.edit', $category) }}" wire:navigate class="s-btn s-btn-ghost s-btn-sm" title="Edit">
                                    <flux:icon.pencil-square class="w-4 h-4" />
                                </a>
                                <button wire:click="deleteCategory({{ $category->id }})"
                                        wire:confirm="Delete this tax category?"
                                        class="s-btn-ghost s-btn-xs text-[var(--red)]">
                                    Delete
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-[var(--text-muted)]">No tax categories configured.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.layout>
</section>
