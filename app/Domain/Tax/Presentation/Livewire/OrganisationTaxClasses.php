<?php

declare(strict_types=1);

namespace App\Domain\Tax\Presentation\Livewire;

use App\Domain\Tax\Actions\DeleteOrganisationTaxClass;
use App\Domain\Tax\Actions\UpdateOrganisationTaxClass;
use App\Domain\Tax\Data\UpdateTaxClassData;
use App\Domain\Tax\Models\OrganisationTaxClass;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')] #[Title('Organisation Tax Classes')]
final class OrganisationTaxClasses extends Component
{
    /** @var Collection<int, OrganisationTaxClass> */
    public $taxClasses;

    public function mount(): void
    {
        $this->loadTaxClasses();
    }

    public function loadTaxClasses(): void
    {
        $this->taxClasses = OrganisationTaxClass::query()->orderBy('name')->get();
    }

    public function setDefault(int $taxClassId): void
    {
        $taxClass = OrganisationTaxClass::findOrFail($taxClassId);

        $taxClass->getConnection()->transaction(function () use ($taxClass) {
            OrganisationTaxClass::query()->where('is_default', true)->update(['is_default' => false]);
            (new UpdateOrganisationTaxClass)($taxClass, UpdateTaxClassData::validateAndCreate([
                'is_default' => true,
            ]));
        });

        $this->loadTaxClasses();
    }

    public function deleteTaxClass(int $taxClassId): void
    {
        $taxClass = OrganisationTaxClass::findOrFail($taxClassId);

        if ($taxClass->is_default) {
            $this->addError('deleteTaxClass', 'The default tax class cannot be deleted.');

            return;
        }

        (new DeleteOrganisationTaxClass)($taxClass);
        $this->loadTaxClasses();
    }

    public function render(): View
    {
        return view('tax::admin.organisation-tax-classes');
    }
}
