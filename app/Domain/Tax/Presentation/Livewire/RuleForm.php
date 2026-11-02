<?php

declare(strict_types=1);

namespace App\Domain\Tax\Presentation\Livewire;

use App\Domain\Tax\Actions\CreateTaxRule;
use App\Domain\Tax\Actions\UpdateTaxRule;
use App\Domain\Tax\Data\CreateTaxRuleData;
use App\Domain\Tax\Data\UpdateTaxRuleData;
use App\Domain\Tax\Models\OrganisationTaxClass;
use App\Domain\Tax\Models\ProductTaxClass;
use App\Domain\Tax\Models\TaxRate;
use App\Domain\Tax\Models\TaxRule;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')] #[Title('Tax Rule')]
final class RuleForm extends Component
{
    public ?int $taxRuleId = null;

    public ?int $organisationTaxClassId = null;

    public ?int $productTaxClassId = null;

    public ?int $taxRateId = null;

    public int $priority = 0;

    public bool $isActive = true;

    public function mount(?TaxRule $taxRule = null): void
    {
        if ($taxRule?->exists) {
            $this->taxRuleId = $taxRule->id;
            $this->organisationTaxClassId = $taxRule->organisation_tax_class_id;
            $this->productTaxClassId = $taxRule->product_tax_class_id;
            $this->taxRateId = $taxRule->tax_rate_id;
            $this->priority = $taxRule->priority;
            $this->isActive = $taxRule->is_active;
        }
    }

    /**
     * @return array{
     *     isEditing: bool,
     *     organisationTaxClasses: Collection<int, OrganisationTaxClass>,
     *     productTaxClasses: Collection<int, ProductTaxClass>,
     *     taxRates: Collection<int, TaxRate>
     * }
     */
    public function with(): array
    {
        return [
            'isEditing' => $this->taxRuleId !== null,
            'organisationTaxClasses' => OrganisationTaxClass::query()->orderBy('name')->get(),
            'productTaxClasses' => ProductTaxClass::query()->orderBy('name')->get(),
            'taxRates' => TaxRate::query()->where('is_active', true)->orderBy('name')->get(),
        ];
    }

    public function save(): void
    {
        if ($this->taxRuleId) {
            $taxRule = TaxRule::findOrFail($this->taxRuleId);
            (new UpdateTaxRule)($taxRule, UpdateTaxRuleData::validateAndCreate([
                'organisation_tax_class_id' => $this->organisationTaxClassId,
                'product_tax_class_id' => $this->productTaxClassId,
                'tax_rate_id' => $this->taxRateId,
                'priority' => $this->priority,
                'is_active' => $this->isActive,
            ]));
        } else {
            (new CreateTaxRule)(CreateTaxRuleData::validateAndCreate([
                'organisation_tax_class_id' => $this->organisationTaxClassId,
                'product_tax_class_id' => $this->productTaxClassId,
                'tax_rate_id' => $this->taxRateId,
                'priority' => $this->priority,
                'is_active' => $this->isActive,
            ]));
        }

        $this->redirect(route('admin.settings.tax.rules'), navigate: true);
    }

    public function render(): View
    {
        return view('tax::admin.rule-form');
    }
}
