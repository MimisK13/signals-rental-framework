<?php

declare(strict_types=1);

namespace App\Domain\Tax\Presentation\Livewire;

use App\Domain\Tax\Actions\CreateZoneTaxRule;
use App\Domain\Tax\Actions\UpdateZoneTaxRule;
use App\Domain\Tax\Data\CreateZoneTaxRuleData;
use App\Domain\Tax\Data\UpdateZoneTaxRuleData;
use App\Domain\Tax\Enums\TaxBuyerType;
use App\Domain\Tax\Enums\TaxResultType;
use App\Domain\Tax\Models\TaxCategory;
use App\Domain\Tax\Models\TaxRule;
use App\Domain\Tax\Models\TaxZone;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')] #[Title('Zone Tax Rule')]
final class ZoneRuleForm extends Component
{
    public ?int $taxRuleId = null;

    public string $name = '';

    public ?string $description = null;

    public ?int $sellerZoneId = null;

    public ?int $buyerZoneId = null;

    public ?string $buyerType = null;

    public ?bool $hasTaxId = null;

    public ?int $taxCategoryId = null;

    public string $resultType = 'standard';

    public ?string $legalText = null;

    public int $priority = 0;

    public bool $isActive = true;

    public function mount(?TaxRule $taxRule = null): void
    {
        if ($taxRule?->exists && $taxRule->isZoneRule()) {
            $this->taxRuleId = $taxRule->id;
            $this->name = (string) $taxRule->name;
            $this->description = $taxRule->description;
            $this->sellerZoneId = $taxRule->seller_zone_id;
            $this->buyerZoneId = $taxRule->buyer_zone_id;
            $this->buyerType = $taxRule->buyer_type?->value;
            $this->hasTaxId = $taxRule->has_tax_id;
            $this->taxCategoryId = $taxRule->tax_category_id;
            $this->resultType = $taxRule->result_type->value;
            $this->legalText = $taxRule->legal_text;
            $this->priority = $taxRule->priority;
            $this->isActive = $taxRule->is_active;
        }
    }

    /**
     * @return array{
     *     isEditing: bool,
     *     taxZones: Collection<int, TaxZone>,
     *     taxCategories: Collection<int, TaxCategory>,
     *     buyerTypes: list<TaxBuyerType>,
     *     resultTypes: list<TaxResultType>
     * }
     */
    public function with(): array
    {
        return [
            'isEditing' => $this->taxRuleId !== null,
            'taxZones' => TaxZone::query()->where('is_active', true)->orderByDesc('priority')->orderBy('name')->get(),
            'taxCategories' => TaxCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'buyerTypes' => TaxBuyerType::cases(),
            'resultTypes' => TaxResultType::cases(),
        ];
    }

    public function save(): void
    {
        $payload = [
            'name' => $this->name,
            'description' => $this->description,
            'seller_zone_id' => $this->sellerZoneId,
            'buyer_zone_id' => $this->buyerZoneId,
            'buyer_type' => $this->buyerType,
            'has_tax_id' => $this->hasTaxId,
            'tax_category_id' => $this->taxCategoryId,
            'result_type' => $this->resultType,
            'legal_text' => $this->legalText,
            'priority' => $this->priority,
            'is_active' => $this->isActive,
        ];

        if ($this->taxRuleId) {
            $taxRule = TaxRule::findOrFail($this->taxRuleId);
            (new UpdateZoneTaxRule)($taxRule, UpdateZoneTaxRuleData::validateAndCreate($payload));
        } else {
            (new CreateZoneTaxRule)(CreateZoneTaxRuleData::validateAndCreate($payload));
        }

        $this->redirect(route('admin.settings.tax.rules'), navigate: true);
    }

    public function render(): View
    {
        return view('tax::admin.zone-rule-form');
    }
}
