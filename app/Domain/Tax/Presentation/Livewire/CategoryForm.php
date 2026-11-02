<?php

declare(strict_types=1);

namespace App\Domain\Tax\Presentation\Livewire;

use App\Domain\Tax\Actions\CreateTaxCategory;
use App\Domain\Tax\Actions\UpdateTaxCategory;
use App\Domain\Tax\Data\CreateTaxCategoryData;
use App\Domain\Tax\Data\UpdateTaxCategoryData;
use App\Domain\Tax\Enums\TaxCategoryType;
use App\Domain\Tax\Models\TaxCategory;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')] #[Title('Tax Category')]
final class CategoryForm extends Component
{
    public ?int $taxCategoryId = null;

    public string $name = '';

    public string $slug = '';

    public string $type = 'standard';

    public ?string $description = null;

    public bool $isDefault = false;

    public bool $isActive = true;

    public function mount(?TaxCategory $taxCategory = null): void
    {
        if ($taxCategory?->exists) {
            $this->taxCategoryId = $taxCategory->id;
            $this->name = $taxCategory->name;
            $this->slug = $taxCategory->slug;
            $this->type = $taxCategory->type->value;
            $this->description = $taxCategory->description;
            $this->isDefault = $taxCategory->is_default;
            $this->isActive = $taxCategory->is_active;
        }
    }

    /** @return array{types: list<TaxCategoryType>} */
    public function with(): array
    {
        return [
            'types' => TaxCategoryType::cases(),
        ];
    }

    public function save(): void
    {
        if ($this->taxCategoryId) {
            $taxCategory = TaxCategory::findOrFail($this->taxCategoryId);
            (new UpdateTaxCategory)($taxCategory, UpdateTaxCategoryData::validateAndCreate([
                'name' => $this->name,
                'slug' => $this->slug,
                'type' => $this->type,
                'description' => $this->description,
                'is_default' => $this->isDefault,
                'is_active' => $this->isActive,
            ]));
        } else {
            (new CreateTaxCategory)(CreateTaxCategoryData::validateAndCreate([
                'name' => $this->name,
                'slug' => $this->slug,
                'type' => $this->type,
                'description' => $this->description,
                'is_default' => $this->isDefault,
                'is_active' => $this->isActive,
            ]));
        }

        $this->redirect(route('admin.settings.tax.categories'), navigate: true);
    }

    public function render(): View
    {
        return view('tax::admin.category-form');
    }
}
