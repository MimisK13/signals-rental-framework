<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Presentation\Livewire;

use App\Domain\Pricing\Actions\CreatePriceCategory;
use App\Domain\Pricing\Actions\UpdatePriceCategory;
use App\Domain\Pricing\Data\CreatePriceCategoryData;
use App\Domain\Pricing\Data\UpdatePriceCategoryData;
use App\Domain\Pricing\Models\PriceCategory;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')] #[Title('Price Category')] final class PriceCategoryForm extends Component
{
    public ?int $categoryId = null;

    public string $name = '';

    public string $description = '';

    public bool $isActive = true;

    /** @var list<array{tier_number: int, label: string, multiplier: string, sort_order: int}> */
    public array $tiers = [];

    public function mount(?PriceCategory $priceCategory = null): void
    {
        if ($priceCategory?->exists) {
            $this->categoryId = $priceCategory->id;
            $this->name = $priceCategory->name;
            $this->description = $priceCategory->description ?? '';
            $this->isActive = $priceCategory->is_active;
            $this->tiers = $priceCategory->tiers()
                ->orderBy('sort_order')
                ->orderBy('tier_number')
                ->get()
                ->map(fn ($tier): array => [
                    'tier_number' => $tier->tier_number,
                    'label' => $tier->label ?? '',
                    'multiplier' => (string) $tier->multiplier,
                    'sort_order' => $tier->sort_order,
                ])
                ->all();
        } else {
            $this->tiers = [
                ['tier_number' => 1, 'label' => 'Day 1', 'multiplier' => '1.0000', 'sort_order' => 0],
            ];
        }
    }

    public function addTier(): void
    {
        $nextNumber = count($this->tiers) + 1;

        $this->tiers[] = [
            'tier_number' => $nextNumber,
            'label' => '',
            'multiplier' => '1.0000',
            'sort_order' => count($this->tiers),
        ];
    }

    public function removeTier(int $index): void
    {
        unset($this->tiers[$index]);
        $this->tiers = array_values($this->tiers);
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        return [
            'isEditing' => $this->categoryId !== null,
        ];
    }

    public function save(): void
    {
        $payload = [
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->isActive,
            'tiers' => $this->tiers,
        ];

        if ($this->categoryId) {
            $category = PriceCategory::findOrFail($this->categoryId);

            (new UpdatePriceCategory)($category, UpdatePriceCategoryData::validateAndCreate($payload));
        } else {
            (new CreatePriceCategory)(CreatePriceCategoryData::validateAndCreate($payload));
        }

        $this->redirect(route('admin.settings.pricing.price-categories'), navigate: true);
    }

    public function render(): View
    {
        return view('pricing::admin.price-category-form', $this->with());
    }
}
