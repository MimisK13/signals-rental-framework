<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Presentation\Livewire;

use App\Domain\Pricing\Actions\DeletePriceCategory;
use App\Domain\Pricing\Models\PriceCategory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')] #[Title('Price Categories')] final class PriceCategories extends Component
{
    /** @var Collection<int, PriceCategory> */
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

    public function render(): View
    {
        return view('pricing::admin.price-categories');
    }
}
