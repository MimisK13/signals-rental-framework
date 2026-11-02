<?php

declare(strict_types=1);

namespace App\Domain\Tax\Presentation\Livewire;

use App\Domain\Tax\Actions\DeleteTaxCategory;
use App\Domain\Tax\Models\TaxCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')] #[Title('Tax Categories')]
final class Categories extends Component
{
    /** @var Collection<int, TaxCategory> */
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

    public function render(): View
    {
        return view('tax::admin.categories');
    }
}
