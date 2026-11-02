<?php

declare(strict_types=1);

namespace App\Domain\Tax\Presentation\Livewire;

use App\Domain\Tax\Actions\CreateProductTaxClass;
use App\Domain\Tax\Actions\UpdateProductTaxClass;
use App\Domain\Tax\Data\CreateTaxClassData;
use App\Domain\Tax\Data\UpdateTaxClassData;
use App\Domain\Tax\Models\ProductTaxClass;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')] #[Title('Product Tax Class')]
final class ProductTaxClassForm extends Component
{
    public ?int $taxClassId = null;

    public string $name = '';

    public string $description = '';

    public bool $isDefault = false;

    public function mount(?ProductTaxClass $productTaxClass = null): void
    {
        if ($productTaxClass?->exists) {
            $this->taxClassId = $productTaxClass->id;
            $this->name = $productTaxClass->name;
            $this->description = $productTaxClass->description ?? '';
            $this->isDefault = $productTaxClass->is_default;
        }
    }

    /** @return array{isEditing: bool} */
    public function with(): array
    {
        return [
            'isEditing' => $this->taxClassId !== null,
        ];
    }

    public function save(): void
    {
        if ($this->taxClassId) {
            $taxClass = ProductTaxClass::findOrFail($this->taxClassId);

            if ($this->isDefault && ! $taxClass->is_default) {
                $taxClass->getConnection()->transaction(function () use ($taxClass) {
                    ProductTaxClass::query()->where('is_default', true)->update(['is_default' => false]);
                    (new UpdateProductTaxClass)($taxClass, UpdateTaxClassData::validateAndCreate([
                        'name' => $this->name,
                        'description' => $this->description,
                        'is_default' => true,
                    ]));
                });
            } else {
                (new UpdateProductTaxClass)($taxClass, UpdateTaxClassData::validateAndCreate([
                    'name' => $this->name,
                    'description' => $this->description,
                    'is_default' => $this->isDefault,
                ]));
            }
        } else {
            $isDefault = $this->isDefault || ProductTaxClass::count() === 0;

            if ($isDefault) {
                ProductTaxClass::query()->getConnection()->transaction(function () {
                    ProductTaxClass::query()->where('is_default', true)->update(['is_default' => false]);
                    (new CreateProductTaxClass)(CreateTaxClassData::validateAndCreate([
                        'name' => $this->name,
                        'description' => $this->description,
                        'is_default' => true,
                    ]));
                });
            } else {
                (new CreateProductTaxClass)(CreateTaxClassData::validateAndCreate([
                    'name' => $this->name,
                    'description' => $this->description,
                    'is_default' => false,
                ]));
            }
        }

        $this->redirect(route('admin.settings.tax.product-tax-classes'), navigate: true);
    }

    public function render(): View
    {
        return view('tax::admin.product-tax-class-form');
    }
}
