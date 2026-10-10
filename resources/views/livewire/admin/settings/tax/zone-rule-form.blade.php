<?php

use App\Actions\Tax\CreateZoneTaxRule;
use App\Actions\Tax\UpdateZoneTaxRule;
use App\Data\Tax\CreateZoneTaxRuleData;
use App\Data\Tax\UpdateZoneTaxRuleData;
use App\Enums\TaxBuyerType;
use App\Enums\TaxResultType;
use App\Models\TaxCategory;
use App\Models\TaxRule;
use App\Models\TaxZone;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Zone Tax Rule')] class extends Component {
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
            $this->resultType = $taxRule->result_type?->value ?? 'standard';
            $this->legalText = $taxRule->legal_text;
            $this->priority = $taxRule->priority;
            $this->isActive = $taxRule->is_active;
        }
    }

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
}; ?>

<section class="w-full">
    <x-admin.layout group="tax" :title="$isEditing ? 'Edit Zone Tax Rule' : 'Create Zone Tax Rule'" :description="$isEditing ? 'Update cross-border tax treatment matching.' : 'Match seller and buyer zones to a tax result type.'">
        <x-slot:breadcrumbs>
            <x-signals.breadcrumb :items="[
                ['label' => 'Tax Rules', 'href' => route('admin.settings.tax.rules')],
                ['label' => $isEditing ? 'Edit Zone Rule' : 'Create Zone Rule'],
            ]" />
        </x-slot:breadcrumbs>

        <form wire:submit="save" class="space-y-6">
            <x-signals.form-section title="Zone Matching">
                <div class="space-y-4">
                    <flux:input wire:model="name" label="Name" required />
                    <flux:textarea wire:model="description" label="Description" />
                    <flux:select wire:model="sellerZoneId" label="Seller zone" required>
                        <flux:select.option value="">Select...</flux:select.option>
                        @foreach($taxZones as $zone)
                            <flux:select.option value="{{ $zone->id }}">{{ $zone->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model="buyerZoneId" label="Buyer zone" required>
                        <flux:select.option value="">Select...</flux:select.option>
                        @foreach($taxZones as $zone)
                            <flux:select.option value="{{ $zone->id }}">{{ $zone->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model="buyerType" label="Buyer type (optional)">
                        <flux:select.option value="">Any</flux:select.option>
                        @foreach($buyerTypes as $type)
                            <flux:select.option value="{{ $type->value }}">{{ $type->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model="hasTaxId" label="Requires validated tax ID">
                        <flux:select.option :value="null">Any</flux:select.option>
                        <flux:select.option :value="true">Yes</flux:select.option>
                        <flux:select.option :value="false">No</flux:select.option>
                    </flux:select>
                    <flux:select wire:model="taxCategoryId" label="Tax category (optional)">
                        <flux:select.option value="">Any</flux:select.option>
                        @foreach($taxCategories as $category)
                            <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model="resultType" label="Result type" required>
                        @foreach($resultTypes as $type)
                            <flux:select.option value="{{ $type->value }}">{{ $type->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:textarea wire:model="legalText" label="Legal text" />
                    <flux:input wire:model="priority" label="Priority" type="number" min="0" />
                    <flux:checkbox wire:model="isActive" label="Active" />
                </div>
            </x-signals.form-section>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">{{ $isEditing ? 'Update' : 'Create' }}</flux:button>
                <flux:button variant="ghost" href="{{ route('admin.settings.tax.rules') }}" wire:navigate.hover>Cancel</flux:button>
            </div>
        </form>
    </x-admin.layout>
</section>
