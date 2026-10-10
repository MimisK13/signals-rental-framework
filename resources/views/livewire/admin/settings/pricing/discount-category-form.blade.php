<?php

use App\Actions\Pricing\CreateDiscountCategory;
use App\Actions\Pricing\UpdateDiscountCategory;
use App\Data\Pricing\CreateDiscountCategoryData;
use App\Data\Pricing\UpdateDiscountCategoryData;
use App\Enums\DiscountRuleScope;
use App\Enums\RateTransactionType;
use App\Models\DiscountCategory;
use App\Models\Product;
use App\Models\ProductGroup;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Discount Category')] class extends Component {
    public ?int $categoryId = null;

    public string $name = '';

    public string $description = '';

    public bool $isActive = true;

    /**
     * @var list<array{
     *     discount_percent: string,
     *     product_group_ids: list<int>,
     *     tag_names: string,
     *     product_ids: list<int>,
     *     transaction_types: list<string>,
     *     valid_from: string,
     *     valid_to: string,
     *     sort_order: int
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
        return array_values(array_map(function (array $rule, int $index): array {
            $criteria = array_filter([
                'product_group_ids' => $rule['product_group_ids'] ?? [],
                'product_ids' => $rule['product_ids'] ?? [],
                'transaction_types' => $rule['transaction_types'] ?? [],
                'tag_names' => $this->parseTagNames($rule['tag_names'] ?? ''),
                'quantity_min' => ($rule['quantity_min'] ?? '') !== '' ? (int) $rule['quantity_min'] : null,
                'quantity_max' => ($rule['quantity_max'] ?? '') !== '' ? (int) $rule['quantity_max'] : null,
            ], fn (mixed $value): bool => is_array($value) ? $value !== [] : $value !== null && $value !== '');

            return [
                'discount_percent' => $rule['discount_percent'],
                'criteria' => $criteria,
                'rule_scope' => $rule['rule_scope'] ?? DiscountRuleScope::PerLine->value,
                'valid_from' => ($rule['valid_from'] ?? '') !== '' ? $rule['valid_from'] : null,
                'valid_to' => ($rule['valid_to'] ?? '') !== '' ? $rule['valid_to'] : null,
                'sort_order' => $rule['sort_order'] ?? $index,
            ];
        }, $this->rules, array_keys($this->rules)));
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
     *     valid_from: string,
     *     valid_to: string,
     *     sort_order: int
     * }
     */
    private function ruleFromModel(\App\Models\DiscountRule $rule): array
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
}; ?>

<section class="w-full">
    <x-admin.layout group="pricing" :title="$isEditing ? 'Edit Discount Category' : 'Create Discount Category'">
        <x-dense::card>
            <form wire:submit="save" class="space-y-6">
                <div class="space-y-4">
                    <flux:input wire:model="name" label="Name" required />
                    <flux:textarea wire:model="description" label="Description" rows="3" />
                    <flux:checkbox wire:model="isActive" label="Active" />
                </div>

                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <flux:heading size="sm">Discount Rules</flux:heading>
                        <flux:button type="button" wire:click="addRule" variant="ghost" size="sm">Add Rule</flux:button>
                    </div>

                    <p class="text-sm text-[var(--text-muted)]">
                        Rules are definitions only in this milestone — matching against opportunity lines arrives in a later phase.
                        Empty criteria match all products; multiple criteria combine with AND logic; tag criteria require every listed tag.
                    </p>

                    @forelse($rules as $index => $rule)
                        <div wire:key="discount-rule-{{ $index }}" class="rounded-lg border border-[var(--border)] p-4 space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="font-medium">Rule {{ $index + 1 }}</span>
                                <button type="button" wire:click="removeRule({{ $index }})" class="s-btn-ghost s-btn-xs text-[var(--red)]">Remove</button>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <flux:input wire:model="rules.{{ $index }}.discount_percent" label="Discount %" type="number" step="0.01" min="0" max="100" required />
                                <flux:select wire:model="rules.{{ $index }}.rule_scope" label="Quantity scope">
                                    @foreach($ruleScopeOptions as $option)
                                        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                    @endforeach
                                </flux:select>
                                <flux:input wire:model="rules.{{ $index }}.quantity_min" label="Min quantity" type="number" min="0" />
                                <flux:input wire:model="rules.{{ $index }}.quantity_max" label="Max quantity" type="number" min="0" />
                                <flux:input wire:model="rules.{{ $index }}.sort_order" label="Sort order" type="number" min="0" />
                                <flux:input wire:model="rules.{{ $index }}.valid_from" label="Valid from" type="datetime-local" />
                                <flux:input wire:model="rules.{{ $index }}.valid_to" label="Valid to" type="datetime-local" />
                            </div>

                            <flux:select wire:model="rules.{{ $index }}.product_group_ids" label="Product groups" multiple>
                                @foreach($productGroups as $group)
                                    <option value="{{ $group->id }}">{{ $group->name }}</option>
                                @endforeach
                            </flux:select>

                            <flux:input wire:model="rules.{{ $index }}.tag_names" label="Tags (comma-separated, all required)" placeholder="lighting, led" />

                            <flux:select wire:model="rules.{{ $index }}.product_ids" label="Products" multiple>
                                @foreach($products as $product)
                                    <option value="{{ $product->id }}">{{ $product->name }}</option>
                                @endforeach
                            </flux:select>

                            <flux:select wire:model="rules.{{ $index }}.transaction_types" label="Transaction types" multiple>
                                @foreach($transactionTypeOptions as $option)
                                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                @endforeach
                            </flux:select>
                        </div>
                    @empty
                        <p class="text-sm text-[var(--text-muted)]">No rules yet. Add a blanket rule or criteria-specific discounts.</p>
                    @endforelse
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <flux:button type="submit" variant="primary">Save</flux:button>
                    <flux:button href="{{ route('admin.settings.pricing.discount-categories') }}" wire:navigate.hover variant="ghost">Cancel</flux:button>
                </div>
            </form>
        </x-dense::card>
    </x-admin.layout>
</section>
