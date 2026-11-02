<?php

declare(strict_types=1);

namespace App\Domain\Tax\Presentation\Livewire;

use App\Domain\Tax\Actions\CreateOrganisationTaxClass;
use App\Domain\Tax\Actions\UpdateOrganisationTaxClass;
use App\Domain\Tax\Data\CreateTaxClassData;
use App\Domain\Tax\Data\UpdateTaxClassData;
use App\Domain\Tax\Models\OrganisationTaxClass;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')] #[Title('Organisation Tax Class')]
final class OrganisationTaxClassForm extends Component
{
    public ?int $taxClassId = null;

    public string $name = '';

    public string $description = '';

    public bool $isDefault = false;

    public function mount(?OrganisationTaxClass $organisationTaxClass = null): void
    {
        if ($organisationTaxClass?->exists) {
            $this->taxClassId = $organisationTaxClass->id;
            $this->name = $organisationTaxClass->name;
            $this->description = $organisationTaxClass->description ?? '';
            $this->isDefault = $organisationTaxClass->is_default;
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
            $taxClass = OrganisationTaxClass::findOrFail($this->taxClassId);

            if ($this->isDefault && ! $taxClass->is_default) {
                $taxClass->getConnection()->transaction(function () use ($taxClass) {
                    OrganisationTaxClass::query()->where('is_default', true)->update(['is_default' => false]);
                    (new UpdateOrganisationTaxClass)($taxClass, UpdateTaxClassData::validateAndCreate([
                        'name' => $this->name,
                        'description' => $this->description,
                        'is_default' => true,
                    ]));
                });
            } else {
                (new UpdateOrganisationTaxClass)($taxClass, UpdateTaxClassData::validateAndCreate([
                    'name' => $this->name,
                    'description' => $this->description,
                    'is_default' => $this->isDefault,
                ]));
            }
        } else {
            $isDefault = $this->isDefault || OrganisationTaxClass::count() === 0;

            if ($isDefault) {
                OrganisationTaxClass::query()->getConnection()->transaction(function () {
                    OrganisationTaxClass::query()->where('is_default', true)->update(['is_default' => false]);
                    (new CreateOrganisationTaxClass)(CreateTaxClassData::validateAndCreate([
                        'name' => $this->name,
                        'description' => $this->description,
                        'is_default' => true,
                    ]));
                });
            } else {
                (new CreateOrganisationTaxClass)(CreateTaxClassData::validateAndCreate([
                    'name' => $this->name,
                    'description' => $this->description,
                    'is_default' => false,
                ]));
            }
        }

        $this->redirect(route('admin.settings.tax.organisation-tax-classes'), navigate: true);
    }

    public function render(): View
    {
        return view('tax::admin.organisation-tax-class-form');
    }
}
