<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Presentation\Livewire;

use App\Domain\Pricing\Actions\CreateSurchargeGroup;
use App\Domain\Pricing\Actions\UpdateSurchargeGroup;
use App\Domain\Pricing\Data\CreateSurchargeGroupData;
use App\Domain\Pricing\Data\UpdateSurchargeGroupData;
use App\Domain\Pricing\Models\Surcharge;
use App\Domain\Pricing\Models\SurchargeGroup;
use App\Domain\Products\Models\Product;
use App\Domain\Products\Models\ProductGroup;
use App\Domain\Rates\Enums\RateTransactionType;
use App\Domain\Tax\Models\ProductTaxClass;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')] #[Title('Surcharge Group')] final class SurchargeGroupForm extends Component
{
    public ?int $groupId = null;

    public string $name = '';

    public string $description = '';

    public bool $isDefault = false;

    public bool $isActive = true;

    /**
     * @var list<array{
     *     name: string,
     *     percent_value?: string,
     *     fixed_value_minor?: string,
     *     product_group_ids?: list<int>,
     *     tag_names?: string,
     *     product_ids?: list<int>,
     *     transaction_types?: list<string>,
     *     product_tax_class_id?: string,
     *     is_default?: bool,
     *     valid_from?: string,
     *     valid_to?: string,
     *     sort_order?: int
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

    /** @return array<string, mixed> */
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
        return array_map(function (array $surcharge, int $index): array {
            $criteria = array_filter([
                'product_group_ids' => $surcharge['product_group_ids'] ?? [],
                'product_ids' => $surcharge['product_ids'] ?? [],
                'transaction_types' => $surcharge['transaction_types'] ?? [],
                'tag_names' => $this->parseTagNames($surcharge['tag_names'] ?? ''),
            ], fn (array $value): bool => $value !== []);

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
        }, $this->surcharges, array_keys($this->surcharges));
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
    private function surchargeFromModel(Surcharge $surcharge): array
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

    public function render(): View
    {
        return view('pricing::admin.surcharge-group-form', $this->with());
    }
}
