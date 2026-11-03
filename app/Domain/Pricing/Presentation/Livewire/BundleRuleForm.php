<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Presentation\Livewire;

use App\Domain\Pricing\Actions\CreateBundleRule;
use App\Domain\Pricing\Actions\UpdateBundleRule;
use App\Domain\Pricing\Data\CreateBundleRuleData;
use App\Domain\Pricing\Data\UpdateBundleRuleData;
use App\Domain\Pricing\Models\BundleRule;
use App\Domain\Products\Models\Product;
use App\Domain\Products\Models\ProductGroup;
use App\Domain\Rates\Enums\RateTransactionType;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')] #[Title('Bundle Rule')] final class BundleRuleForm extends Component
{
    public ?int $ruleId = null;

    public string $name = '';

    public string $description = '';

    public string $pricingMode = 'percent';

    public string $bundlePercent = '10.00';

    public string $bundleFixedMinor = '';

    /** @var list<int> */
    public array $productIds = [];

    public bool $isActive = true;

    public int $sortOrder = 0;

    public function mount(?BundleRule $bundleRule = null): void
    {
        if ($bundleRule?->exists) {
            $this->ruleId = $bundleRule->id;
            $this->name = $bundleRule->name;
            $this->description = $bundleRule->description ?? '';
            $this->pricingMode = $bundleRule->isFixed() ? 'fixed' : 'percent';
            $this->bundlePercent = (string) ($bundleRule->bundle_percent ?? '10.00');
            $this->bundleFixedMinor = $bundleRule->bundle_fixed_price_minor !== null
                ? (string) $bundleRule->bundle_fixed_price_minor
                : '';
            $this->productIds = array_map(intval(...), $bundleRule->criteria['product_ids'] ?? []);
            $this->isActive = $bundleRule->is_active;
            $this->sortOrder = $bundleRule->sort_order;
        }
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        return [
            'isEditing' => $this->ruleId !== null,
            'productGroups' => ProductGroup::query()->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()->orderBy('name')->limit(200)->get(['id', 'name']),
            'transactionTypeOptions' => collect(RateTransactionType::cases())
                ->map(fn (RateTransactionType $type): array => [
                    'value' => $type->value,
                    'label' => $type->label(),
                ])
                ->all(),
        ];
    }

    public function save(): void
    {
        $criteria = $this->productIds !== [] ? ['product_ids' => $this->productIds] : [];

        $payload = [
            'name' => $this->name,
            'description' => $this->description !== '' ? $this->description : null,
            'bundle_percent' => $this->pricingMode === 'percent' ? $this->bundlePercent : null,
            'bundle_fixed_price_minor' => $this->pricingMode === 'fixed' ? (int) $this->bundleFixedMinor : null,
            'criteria' => $criteria,
            'is_active' => $this->isActive,
            'sort_order' => $this->sortOrder,
        ];

        if ($this->ruleId) {
            $rule = BundleRule::findOrFail($this->ruleId);
            (new UpdateBundleRule)($rule, UpdateBundleRuleData::validateAndCreate($payload));
        } else {
            (new CreateBundleRule)(CreateBundleRuleData::validateAndCreate($payload));
        }

        $this->redirect(route('admin.settings.pricing.bundle-rules'), navigate: true);
    }

    public function render(): View
    {
        return view('pricing::admin.bundle-rule-form', $this->with());
    }
}
