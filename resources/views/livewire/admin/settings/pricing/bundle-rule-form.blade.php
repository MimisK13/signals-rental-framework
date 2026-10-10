<?php

use App\Actions\Pricing\CreateBundleRule;
use App\Actions\Pricing\UpdateBundleRule;
use App\Data\Pricing\CreateBundleRuleData;
use App\Data\Pricing\UpdateBundleRuleData;
use App\Enums\RateTransactionType;
use App\Models\BundleRule;
use App\Models\Product;
use App\Models\ProductGroup;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Bundle Rule')] class extends Component {
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
}; ?>

<section class="w-full">
    <x-admin.layout group="pricing" :title="$isEditing ? 'Edit Bundle Rule' : 'Create Bundle Rule'">
        <x-dense::card>
            <form wire:submit="save" class="space-y-6">
                <div class="space-y-4">
                    <flux:input wire:model="name" label="Name" required />
                    <flux:textarea wire:model="description" label="Description" rows="3" />
                    <flux:checkbox wire:model="isActive" label="Active" />
                    <flux:input wire:model="sortOrder" label="Sort order" type="number" min="0" />
                </div>

                <div class="space-y-4">
                    <flux:select wire:model.live="pricingMode" label="Bundle pricing mode">
                        <option value="percent">Percentage reduction</option>
                        <option value="fixed">Fixed bundle price (minor units)</option>
                    </flux:select>

                    @if($pricingMode === 'percent')
                        <flux:input wire:model="bundlePercent" label="Bundle discount %" type="number" step="0.01" min="0" max="100" required />
                    @else
                        <flux:input wire:model="bundleFixedMinor" label="Fixed bundle price (minor units)" type="number" min="0" required />
                    @endif
                </div>

                <flux:select wire:model="productIds" label="Bundle products (all required in the same revenue group)" multiple>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}">{{ $product->name }}</option>
                    @endforeach
                </flux:select>

                <div class="flex items-center gap-3 pt-2">
                    <flux:button type="submit" variant="primary">Save</flux:button>
                    <flux:button href="{{ route('admin.settings.pricing.bundle-rules') }}" wire:navigate.hover variant="ghost">Cancel</flux:button>
                </div>
            </form>
        </x-dense::card>
    </x-admin.layout>
</section>
