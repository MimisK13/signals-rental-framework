<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Presentation\Livewire;

use App\Domain\Pricing\Actions\DeleteDiscountCategory;
use App\Domain\Pricing\Models\DiscountCategory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')] #[Title('Discount Categories')] final class DiscountCategories extends Component
{
    /** @var Collection<int, DiscountCategory> */
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

    public function render(): View
    {
        return view('pricing::admin.discount-categories');
    }
}
