<?php

declare(strict_types=1);

namespace App\Domain\Tax\Presentation\Livewire;

use App\Domain\Tax\Actions\DeleteProductTaxClass;
use App\Domain\Tax\Actions\UpdateProductTaxClass;
use App\Domain\Tax\Data\UpdateTaxClassData;
use App\Domain\Tax\Models\ProductTaxClass;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')] #[Title('Product Tax Classes')]
final class ProductTaxClasses extends Component
{
    /** @var Collection<int, ProductTaxClass> */
    public $taxClasses;

    public function mount(): void
    {
        $this->loadTaxClasses();
    }

    public function loadTaxClasses(): void
    {
        $this->taxClasses = ProductTaxClass::query()->orderBy('name')->get();
    }

    public function setDefault(int $taxClassId): void
    {
        $taxClass = ProductTaxClass::findOrFail($taxClassId);

        $taxClass->getConnection()->transaction(function () use ($taxClass) {
            ProductTaxClass::query()->where('is_default', true)->update(['is_default' => false]);
            (new UpdateProductTaxClass)($taxClass, UpdateTaxClassData::validateAndCreate([
                'is_default' => true,
            ]));
        });

        $this->loadTaxClasses();
    }

    public function deleteTaxClass(int $taxClassId): void
    {
        $taxClass = ProductTaxClass::findOrFail($taxClassId);

        if ($taxClass->is_default) {
            $this->addError('deleteTaxClass', 'The default tax class cannot be deleted.');

            return;
        }

        (new DeleteProductTaxClass)($taxClass);
        $this->loadTaxClasses();
    }

    public function render(): View
    {
        return view('tax::admin.product-tax-classes');
    }
}
