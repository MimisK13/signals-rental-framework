<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Presentation\Livewire;

use App\Domain\Pricing\Actions\CreateDiscountCategory;
use App\Domain\Pricing\Actions\UpdateDiscountCategory;
use App\Domain\Pricing\Data\CreateDiscountCategoryData;
use App\Domain\Pricing\Data\UpdateDiscountCategoryData;
use App\Domain\Pricing\Enums\DiscountRuleScope;
use App\Domain\Pricing\Models\DiscountCategory;
use App\Domain\Pricing\Models\DiscountRule;
use App\Domain\Products\Models\Product;
use App\Domain\Products\Models\ProductGroup;
use App\Domain\Rates\Enums\RateTransactionType;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')] #[Title('Discount Category')] final class DiscountCategoryForm extends Component
{
    public ?int $categoryId = null;

    public string $name = '';

    public string $description = '';

    public bool $isActive = true;

    /**
     * @var list<array{
     *     discount_percent: string,
     *     product_group_ids?: list<int>,
     *     tag_names?: string,
     *     product_ids?: list<int>,
     *     transaction_types?: list<string>,
     *     quantity_min?: string,
     *     quantity_max?: string,
     *     rule_scope?: string,
     *     valid_from?: string,
     *     valid_to?: string,
     *     sort_order?: int
     * }>
     */
    public array $rules = [];

    public function mount(?DiscountCategory $discountCategory = null): void
    {
        if ($discountCategory?->exists) {
            $this->categoryId = $discountCategory->id;
            $this->name = $discountCategory->name;
            $this->description = $discountCategory->description ?? '';
            $this->isActive = $discountCategory->is_active;
            $this->rules = $discountCategory->rules()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn ($rule): array => $this->ruleFromModel($rule))
                ->all();
        }
    }

    public function addRule(): void
    {
        $this->rules[] = [
            'discount_percent' => '10.00',
            'product_group_ids' => [],
            'tag_names' => '',
            'product_ids' => [],
            'transaction_types' => [],
            'quantity_min' => '',
            'quantity_max' => '',
            'rule_scope' => DiscountRuleScope::PerLine->value,
            'valid_from' => '',
            'valid_to' => '',
            'sort_order' => count($this->rules),
        ];
    }

    public function removeRule(int $index): void
    {
        unset($this->rules[$index]);
        $this->rules = array_values($this->rules);
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        return [
            'isEditing' => $this->categoryId !== null,
            'productGroups' => ProductGroup::query()->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()->orderBy('name')->limit(200)->get(['id', 'name']),
            'transactionTypeOptions' => collect(RateTransactionType::cases())
                ->map(fn (RateTransactionType $type): array => [
                    'value' => $type->value,
                    'label' => $type->label(),
                ])
                ->all(),
            'ruleScopeOptions' => collect(DiscountRuleScope::cases())
                ->map(fn (DiscountRuleScope $scope): array => [
                    'value' => $scope->value,
                    'label' => $scope->label(),
                ])
                ->all(),
        ];
    }

    public function save(): void
    {
        $payload = [
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->isActive,
            'rules' => $this->normalizedRules(),
        ];

        if ($this->categoryId) {
            $category = DiscountCategory::findOrFail($this->categoryId);

            (new UpdateDiscountCategory)($category, UpdateDiscountCategoryData::validateAndCreate($payload));
        } else {
            (new CreateDiscountCategory)(CreateDiscountCategoryData::validateAndCreate($payload));
        }

        $this->redirect(route('admin.settings.pricing.discount-categories'), navigate: true);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizedRules(): array
    {
        return array_map(function (array $rule, int $index): array {
            $criteria = array_filter([
                'product_group_ids' => $rule['product_group_ids'] ?? [],
                'product_ids' => $rule['product_ids'] ?? [],
                'transaction_types' => $rule['transaction_types'] ?? [],
                'tag_names' => $this->parseTagNames($rule['tag_names'] ?? ''),
                'quantity_min' => ($rule['quantity_min'] ?? '') !== '' ? (int) $rule['quantity_min'] : null,
                'quantity_max' => ($rule['quantity_max'] ?? '') !== '' ? (int) $rule['quantity_max'] : null,
            ], fn (mixed $value): bool => is_array($value) ? $value !== [] : $value !== null);

            return [
                'discount_percent' => $rule['discount_percent'],
                'criteria' => $criteria,
                'rule_scope' => $rule['rule_scope'] ?? DiscountRuleScope::PerLine->value,
                'valid_from' => ($rule['valid_from'] ?? '') !== '' ? $rule['valid_from'] : null,
                'valid_to' => ($rule['valid_to'] ?? '') !== '' ? $rule['valid_to'] : null,
                'sort_order' => $rule['sort_order'] ?? $index,
            ];
        }, $this->rules, array_keys($this->rules));
    }

    /**
     * @return list<string>
     */
    private function parseTagNames(string $value): array
    {
        if ($value === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            trim(...),
            explode(',', $value),
        )));
    }

    /**
     * @return array{
     *     discount_percent: string,
     *     product_group_ids: list<int>,
     *     tag_names: string,
     *     product_ids: list<int>,
     *     transaction_types: list<string>,
     *     quantity_min?: string,
     *     quantity_max?: string,
     *     rule_scope?: string,
     *     valid_from?: string,
     *     valid_to?: string,
     *     sort_order: int
     * }
     */
    private function ruleFromModel(DiscountRule $rule): array
    {
        $criteria = $rule->criteria ?? [];

        return [
            'discount_percent' => (string) $rule->discount_percent,
            'product_group_ids' => array_map(intval(...), $criteria['product_group_ids'] ?? []),
            'tag_names' => implode(', ', $criteria['tag_names'] ?? []),
            'product_ids' => array_map(intval(...), $criteria['product_ids'] ?? []),
            'transaction_types' => $criteria['transaction_types'] ?? [],
            'quantity_min' => isset($criteria['quantity_min']) ? (string) $criteria['quantity_min'] : '',
            'quantity_max' => isset($criteria['quantity_max']) ? (string) $criteria['quantity_max'] : '',
            'rule_scope' => ($rule->rule_scope ?? DiscountRuleScope::PerLine)->value,
            'valid_from' => $rule->valid_from?->format('Y-m-d\TH:i') ?? '',
            'valid_to' => $rule->valid_to?->format('Y-m-d\TH:i') ?? '',
            'sort_order' => $rule->sort_order,
        ];
    }

    public function render(): View
    {
        return view('pricing::admin.discount-category-form', $this->with());
    }
}
