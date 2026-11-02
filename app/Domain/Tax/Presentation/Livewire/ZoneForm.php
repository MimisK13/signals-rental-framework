<?php

declare(strict_types=1);

namespace App\Domain\Tax\Presentation\Livewire;

use App\Domain\Tax\Actions\CreateTaxZone;
use App\Domain\Tax\Actions\UpdateTaxZone;
use App\Domain\Tax\Data\CreateTaxZoneData;
use App\Domain\Tax\Data\UpdateTaxZoneData;
use App\Domain\Tax\Models\TaxZone;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')] #[Title('Tax Zone')]
final class ZoneForm extends Component
{
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

    public function render(): View
    {
        return view('tax::admin.zone-form');
    }
}
