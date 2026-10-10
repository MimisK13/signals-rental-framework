<?php

use App\Actions\Pricing\CreatePriceCategory;
use App\Actions\Pricing\UpdatePriceCategory;
use App\Data\Pricing\CreatePriceCategoryData;
use App\Data\Pricing\UpdatePriceCategoryData;
use App\Models\PriceCategory;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Price Category')] class extends Component {
    public ?int $categoryId = null;

    public string $name = '';

    public string $description = '';

    public bool $isActive = true;

    /** @var list<array{tier_number: int, label: string, multiplier: string, sort_order: int}> */
    public array $tiers = [];

    public function mount(?PriceCategory $priceCategory = null): void
    {
        if ($priceCategory?->exists) {
            $this->categoryId = $priceCategory->id;
            $this->name = $priceCategory->name;
            $this->description = $priceCategory->description ?? '';
            $this->isActive = $priceCategory->is_active;
            $this->tiers = $priceCategory->tiers()
                ->orderBy('sort_order')
                ->orderBy('tier_number')
                ->get()
                ->map(fn ($tier): array => [
                    'tier_number' => $tier->tier_number,
                    'label' => $tier->label ?? '',
                    'multiplier' => (string) $tier->multiplier,
                    'sort_order' => $tier->sort_order,
                ])
                ->all();
        } else {
            $this->tiers = [
                ['tier_number' => 1, 'label' => 'Day 1', 'multiplier' => '1.0000', 'sort_order' => 0],
            ];
        }
    }

    public function addTier(): void
    {
        $nextNumber = count($this->tiers) + 1;

        $this->tiers[] = [
            'tier_number' => $nextNumber,
            'label' => '',
            'multiplier' => '1.0000',
            'sort_order' => count($this->tiers),
        ];
    }

    public function removeTier(int $index): void
    {
        unset($this->tiers[$index]);
        $this->tiers = array_values($this->tiers);
    }

    public function with(): array
    {
        return [
            'isEditing' => $this->categoryId !== null,
        ];
    }

    public function save(): void
    {
        $payload = [
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->isActive,
            'tiers' => $this->tiers,
        ];

        if ($this->categoryId) {
            $category = PriceCategory::findOrFail($this->categoryId);

            (new UpdatePriceCategory)($category, UpdatePriceCategoryData::validateAndCreate($payload));
        } else {
            (new CreatePriceCategory)(CreatePriceCategoryData::validateAndCreate($payload));
        }

        $this->redirect(route('admin.settings.pricing.price-categories'), navigate: true);
    }
}; ?>

<section class="w-full">
    <x-admin.layout group="pricing" :title="$isEditing ? 'Edit Price Category' : 'Create Price Category'">
        <x-signals.card>
            <form wire:submit="save" class="space-y-6">
                <div class="space-y-4">
                    <flux:input wire:model="name" label="Name" required />
                    <flux:textarea wire:model="description" label="Description" rows="3" />
                    <flux:checkbox wire:model="isActive" label="Active" />
                </div>

                <x-signals.form-section title="Tier Multipliers">
                    <div class="space-y-3">
                        @foreach($tiers as $index => $tier)
                            <div class="grid grid-cols-4 gap-3 items-end max-sm:grid-cols-1" wire:key="tier-{{ $index }}">
                                <flux:input wire:model="tiers.{{ $index }}.tier_number" label="Tier #" type="number" min="1" required />
                                <flux:input wire:model="tiers.{{ $index }}.label" label="Label" />
                                <flux:input wire:model="tiers.{{ $index }}.multiplier" label="Multiplier" required />
                                <div class="flex gap-2">
                                    <flux:button type="button" wire:click="removeTier({{ $index }})" variant="ghost" class="text-[var(--red)]">
                                        Remove
                                    </flux:button>
                                </div>
                            </div>
                        @endforeach

                        <flux:button type="button" wire:click="addTier" variant="ghost">Add Tier</flux:button>
                    </div>
                </x-signals.form-section>

                <div class="flex items-center gap-3 pt-2">
                    <flux:button type="submit" variant="primary">Save</flux:button>
                    <flux:button href="{{ route('admin.settings.pricing.price-categories') }}" wire:navigate.hover variant="ghost">Cancel</flux:button>
                </div>
            </form>
        </x-signals.card>
    </x-admin.layout>
</section>
