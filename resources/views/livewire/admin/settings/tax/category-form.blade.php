<?php

use App\Actions\Tax\CreateTaxCategory;
use App\Actions\Tax\UpdateTaxCategory;
use App\Data\Tax\CreateTaxCategoryData;
use App\Data\Tax\UpdateTaxCategoryData;
use App\Enums\TaxCategoryType;
use App\Models\TaxCategory;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Tax Category')] class extends Component {
    public ?int $taxCategoryId = null;
    public string $name = '';
    public string $slug = '';
    public string $type = 'standard';
    public ?string $description = null;
    public bool $isDefault = false;
    public bool $isActive = true;

    public function mount(?TaxCategory $taxCategory = null): void
    {
        if ($taxCategory?->exists) {
            $this->taxCategoryId = $taxCategory->id;
            $this->name = $taxCategory->name;
            $this->slug = $taxCategory->slug;
            $this->type = $taxCategory->type->value;
            $this->description = $taxCategory->description;
            $this->isDefault = $taxCategory->is_default;
            $this->isActive = $taxCategory->is_active;
        }
    }

    public function with(): array
    {
        return [
            'types' => TaxCategoryType::cases(),
        ];
    }

    public function save(): void
    {
        if ($this->taxCategoryId) {
            $taxCategory = TaxCategory::findOrFail($this->taxCategoryId);
            (new UpdateTaxCategory)($taxCategory, UpdateTaxCategoryData::validateAndCreate([
                'name' => $this->name,
                'slug' => $this->slug,
                'type' => $this->type,
                'description' => $this->description,
                'is_default' => $this->isDefault,
                'is_active' => $this->isActive,
            ]));
        } else {
            (new CreateTaxCategory)(CreateTaxCategoryData::validateAndCreate([
                'name' => $this->name,
                'slug' => $this->slug,
                'type' => $this->type,
                'description' => $this->description,
                'is_default' => $this->isDefault,
                'is_active' => $this->isActive,
            ]));
        }

        $this->redirect(route('admin.settings.tax.categories'), navigate: true);
    }
}; ?>

<section class="w-full">
    <x-admin.layout group="tax" :title="$taxCategoryId ? 'Edit Tax Category' : 'Create Tax Category'" :description="$taxCategoryId ? 'Update category metadata.' : 'Define a product tax category.'">
        <x-slot:breadcrumbs>
            <x-signals.breadcrumb :items="[
                ['label' => 'Tax Categories', 'href' => route('admin.settings.tax.categories')],
                ['label' => $taxCategoryId ? 'Edit' : 'Create'],
            ]" />
        </x-slot:breadcrumbs>

        <form wire:submit="save" class="space-y-6">
            <x-signals.form-section title="Category Details">
                <div class="space-y-4">
                    <flux:input wire:model="name" label="Name" required />
                    <flux:input wire:model="slug" label="Slug" required />
                    <flux:select wire:model="type" label="Type" required>
                        @foreach($types as $categoryType)
                            <flux:select.option value="{{ $categoryType->value }}">{{ $categoryType->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:textarea wire:model="description" label="Description" />
                    <flux:checkbox wire:model="isDefault" label="Default category" />
                    <flux:checkbox wire:model="isActive" label="Active" />
                </div>
            </x-signals.form-section>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">{{ $taxCategoryId ? 'Update' : 'Create' }}</flux:button>
                <flux:button variant="ghost" href="{{ route('admin.settings.tax.categories') }}" wire:navigate>Cancel</flux:button>
            </div>
        </form>
    </x-admin.layout>
</section>
