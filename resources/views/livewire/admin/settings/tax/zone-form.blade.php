<?php

use App\Actions\Tax\CreateTaxZone;
use App\Actions\Tax\UpdateTaxZone;
use App\Data\Tax\CreateTaxZoneData;
use App\Data\Tax\UpdateTaxZoneData;
use App\Models\TaxZone;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Tax Zone')] class extends Component {
    public ?int $taxZoneId = null;
    public string $name = '';
    public string $slug = '';
    public ?string $description = null;
    public int $priority = 0;
    public bool $isActive = true;

    public function mount(?TaxZone $taxZone = null): void
    {
        if ($taxZone?->exists) {
            $this->taxZoneId = $taxZone->id;
            $this->name = $taxZone->name;
            $this->slug = $taxZone->slug;
            $this->description = $taxZone->description;
            $this->priority = $taxZone->priority;
            $this->isActive = $taxZone->is_active;
        }
    }

    public function save(): void
    {
        if ($this->taxZoneId) {
            $taxZone = TaxZone::findOrFail($this->taxZoneId);
            (new UpdateTaxZone)($taxZone, UpdateTaxZoneData::validateAndCreate([
                'name' => $this->name,
                'slug' => $this->slug,
                'description' => $this->description,
                'priority' => $this->priority,
                'is_active' => $this->isActive,
            ]));
        } else {
            (new CreateTaxZone)(CreateTaxZoneData::validateAndCreate([
                'name' => $this->name,
                'slug' => $this->slug,
                'description' => $this->description,
                'priority' => $this->priority,
                'is_active' => $this->isActive,
            ]));
        }

        $this->redirect(route('admin.settings.tax.zones'), navigate: true);
    }
}; ?>

<section class="w-full">
    <x-admin.layout group="tax" :title="$taxZoneId ? 'Edit Tax Zone' : 'Create Tax Zone'" :description="$taxZoneId ? 'Update zone metadata.' : 'Define a geographic tax zone.'">
        <x-slot:breadcrumbs>
            <x-signals.breadcrumb :items="[
                ['label' => 'Tax Zones', 'href' => route('admin.settings.tax.zones')],
                ['label' => $taxZoneId ? 'Edit' : 'Create'],
            ]" />
        </x-slot:breadcrumbs>

        <form wire:submit="save" class="space-y-6">
            <x-signals.form-section title="Zone Details">
                <div class="space-y-4">
                    <flux:input wire:model="name" label="Name" required />
                    <flux:input wire:model="slug" label="Slug" required />
                    <flux:textarea wire:model="description" label="Description" />
                    <flux:input wire:model="priority" label="Priority" type="number" min="0" />
                    <flux:checkbox wire:model="isActive" label="Active" />
                </div>
            </x-signals.form-section>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">{{ $taxZoneId ? 'Update' : 'Create' }}</flux:button>
                <flux:button variant="ghost" href="{{ route('admin.settings.tax.zones') }}" wire:navigate>Cancel</flux:button>
            </div>
        </form>
    </x-admin.layout>
</section>
