<?php

use App\Actions\Pricing\CreateSurchargeGroup;
use App\Actions\Pricing\UpdateSurchargeGroup;
use App\Data\Pricing\CreateSurchargeGroupData;
use App\Data\Pricing\UpdateSurchargeGroupData;
use App\Enums\RateTransactionType;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductTaxClass;
use App\Models\SurchargeGroup;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Surcharge Group')] class extends Component {
    public ?int $groupId = null;

    public string $name = '';

    public string $description = '';

    public bool $isDefault = false;

    public bool $isActive = true;

    /**
     * @var list<array{
     *     name: string,
     *     percent_value: string,
     *     fixed_value_minor: string,
     *     product_group_ids: list<int>,
     *     tag_names: string,
     *     product_ids: list<int>,
     *     transaction_types: list<string>,
     *     product_tax_class_id: string,
     *     is_default: bool,
     *     valid_from: string,
     *     valid_to: string,
     *     sort_order: int
     * }>
     */
    public array $surcharges = [];

    public function mount(?SurchargeGroup $surchargeGroup = null): void
    {
        if ($surchargeGroup?->exists) {
            $this->groupId = $surchargeGroup->id;
            $this->name = $surchargeGroup->name;
            $this->description = $surchargeGroup->description ?? '';
            $this->isDefault = $surchargeGroup->is_default;
            $this->isActive = $surchargeGroup->is_active;
            $this->surcharges = $surchargeGroup->surcharges()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn ($surcharge): array => $this->surchargeFromModel($surcharge))
                ->all();
        }
    }

    public function addSurcharge(): void
    {
        $this->surcharges[] = [
            'name' => 'New surcharge',
            'percent_value' => '5.00',
            'fixed_value_minor' => '',
            'product_group_ids' => [],
            'tag_names' => '',
            'product_ids' => [],
            'transaction_types' => [],
            'product_tax_class_id' => '',
            'is_default' => true,
            'valid_from' => '',
            'valid_to' => '',
            'sort_order' => count($this->surcharges),
        ];
    }

    public function removeSurcharge(int $index): void
    {
        unset($this->surcharges[$index]);
        $this->surcharges = array_values($this->surcharges);
    }

    public function with(): array
    {
        return [
            'isEditing' => $this->groupId !== null,
            'productGroups' => ProductGroup::query()->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()->orderBy('name')->limit(200)->get(['id', 'name']),
            'productTaxClasses' => ProductTaxClass::query()->orderBy('name')->get(['id', 'name']),
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
        $payload = [
            'name' => $this->name,
            'description' => $this->description,
            'is_default' => $this->isDefault,
            'is_active' => $this->isActive,
            'surcharges' => $this->normalizedSurcharges(),
        ];

        if ($this->groupId) {
            $group = SurchargeGroup::findOrFail($this->groupId);

            (new UpdateSurchargeGroup)($group, UpdateSurchargeGroupData::validateAndCreate($payload));
        } else {
            (new CreateSurchargeGroup)(CreateSurchargeGroupData::validateAndCreate($payload));
        }

        $this->redirect(route('admin.settings.pricing.surcharge-groups'), navigate: true);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizedSurcharges(): array
    {
        return array_values(array_map(function (array $surcharge, int $index): array {
            $criteria = array_filter([
                'product_group_ids' => $surcharge['product_group_ids'] ?? [],
                'product_ids' => $surcharge['product_ids'] ?? [],
                'transaction_types' => $surcharge['transaction_types'] ?? [],
                'tag_names' => $this->parseTagNames($surcharge['tag_names'] ?? ''),
            ], fn (mixed $value): bool => is_array($value) ? $value !== [] : $value !== null && $value !== '');

            $fixed = ($surcharge['fixed_value_minor'] ?? '') !== ''
                ? (int) $surcharge['fixed_value_minor']
                : null;
            $percent = ($surcharge['percent_value'] ?? '') !== '' && $fixed === null
                ? $surcharge['percent_value']
                : null;

            return [
                'name' => $surcharge['name'],
                'percent_value' => $percent,
                'fixed_value_minor' => $fixed,
                'criteria' => $criteria,
                'is_default' => $surcharge['is_default'] ?? false,
                'product_tax_class_id' => ($surcharge['product_tax_class_id'] ?? '') !== ''
                    ? (int) $surcharge['product_tax_class_id']
                    : null,
                'valid_from' => ($surcharge['valid_from'] ?? '') !== '' ? $surcharge['valid_from'] : null,
                'valid_to' => ($surcharge['valid_to'] ?? '') !== '' ? $surcharge['valid_to'] : null,
                'sort_order' => $surcharge['sort_order'] ?? $index,
            ];
        }, $this->surcharges, array_keys($this->surcharges)));
    }

    /**
     * @return list<string>
     */
    private function parseTagNames(string $value): array
    {
        if ($value === '') {
            return [];
        }

        return array_values(array_filter(array_map(trim(...), explode(',', $value))));
    }

    /**
     * @return array<string, mixed>
     */
    private function surchargeFromModel(\App\Models\Surcharge $surcharge): array
    {
        $criteria = $surcharge->criteria ?? [];

        return [
            'name' => $surcharge->name,
            'percent_value' => $surcharge->percent_value !== null ? (string) $surcharge->percent_value : '',
            'fixed_value_minor' => $surcharge->fixed_value_minor !== null ? (string) $surcharge->fixed_value_minor : '',
            'product_group_ids' => array_map(intval(...), $criteria['product_group_ids'] ?? []),
            'tag_names' => implode(', ', $criteria['tag_names'] ?? []),
            'product_ids' => array_map(intval(...), $criteria['product_ids'] ?? []),
            'transaction_types' => $criteria['transaction_types'] ?? [],
            'product_tax_class_id' => $surcharge->product_tax_class_id !== null ? (string) $surcharge->product_tax_class_id : '',
            'is_default' => $surcharge->is_default,
            'valid_from' => $surcharge->valid_from?->format('Y-m-d\TH:i') ?? '',
            'valid_to' => $surcharge->valid_to?->format('Y-m-d\TH:i') ?? '',
            'sort_order' => $surcharge->sort_order,
        ];
    }
}; ?>

<section class="w-full">
    <x-admin.layout group="pricing" :title="$isEditing ? 'Edit Surcharge Group' : 'Create Surcharge Group'">
        <x-signals.card>
            <form wire:submit="save" class="space-y-6">
                <div class="space-y-4">
                    <flux:input wire:model="name" label="Name" required />
                    <flux:textarea wire:model="description" label="Description" rows="3" />
                    <flux:checkbox wire:model="isDefault" label="Default group (auto-enabled on new opportunities)" />
                    <flux:checkbox wire:model="isActive" label="Active" />
                </div>

                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <flux:heading size="sm">Surcharges</flux:heading>
                        <flux:button type="button" wire:click="addSurcharge" variant="ghost" size="sm">Add Surcharge</flux:button>
                    </div>

                    @forelse($surcharges as $index => $surcharge)
                        <div wire:key="surcharge-{{ $index }}" class="rounded-lg border border-[var(--border)] p-4 space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="font-medium">Surcharge {{ $index + 1 }}</span>
                                <flux:button type="button" wire:click="removeSurcharge({{ $index }})" variant="ghost" size="sm">Remove</flux:button>
                            </div>
                            <flux:input wire:model="surcharges.{{ $index }}.name" label="Name" required />
                            <div class="grid gap-4 md:grid-cols-2">
                                <flux:input wire:model="surcharges.{{ $index }}.percent_value" label="Percent value (%)" />
                                <flux:input wire:model="surcharges.{{ $index }}.fixed_value_minor" label="Fixed value (minor units)" />
                            </div>
                            <flux:select wire:model="surcharges.{{ $index }}.product_tax_class_id" label="Tax class">
                                <option value="">Inherit from product / default</option>
                                @foreach($productTaxClasses as $taxClass)
                                    <option value="{{ $taxClass->id }}">{{ $taxClass->name }}</option>
                                @endforeach
                            </flux:select>
                            <flux:checkbox wire:model="surcharges.{{ $index }}.is_default" label="Default (applies when group is active)" />
                        </div>
                    @empty
                        <p class="text-sm text-[var(--text-muted)]">No surcharges yet.</p>
                    @endforelse
                </div>

                <div class="flex justify-end gap-2">
                    <flux:button href="{{ route('admin.settings.pricing.surcharge-groups') }}" wire:navigate variant="ghost">Cancel</flux:button>
                    <flux:button type="submit" variant="primary">Save</flux:button>
                </div>
            </form>
        </x-signals.card>
    </x-admin.layout>
</section>
