<?php

declare(strict_types=1);

namespace App\Domain\Tax\Presentation\Livewire;

use App\Domain\Tax\Actions\CreateTaxRate;
use App\Domain\Tax\Actions\UpdateTaxRate;
use App\Domain\Tax\Data\CreateTaxRateData;
use App\Domain\Tax\Data\UpdateTaxRateData;
use App\Domain\Tax\Models\TaxRate;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')] #[Title('Tax Rate')]
final class RateForm extends Component
{
    public ?int $taxRateId = null;

    public string $name = '';

    public string $description = '';

    public string $rate = '0.00';

    public bool $isActive = true;

    public function mount(?TaxRate $taxRate = null): void
    {
        if ($taxRate?->exists) {
            $this->taxRateId = $taxRate->id;
            $this->name = $taxRate->name;
            $this->description = $taxRate->description ?? '';
            $this->rate = number_format((float) $taxRate->rate, 2);
            $this->isActive = $taxRate->is_active;
        }
    }

    /** @return array{isEditing: bool} */
    public function with(): array
    {
        return [
            'isEditing' => $this->taxRateId !== null,
        ];
    }

    public function save(): void
    {
        if ($this->taxRateId) {
            $taxRate = TaxRate::findOrFail($this->taxRateId);
            (new UpdateTaxRate)($taxRate, UpdateTaxRateData::validateAndCreate([
                'name' => $this->name,
                'description' => $this->description,
                'rate' => $this->rate,
                'is_active' => $this->isActive,
            ]));
        } else {
            (new CreateTaxRate)(CreateTaxRateData::validateAndCreate([
                'name' => $this->name,
                'description' => $this->description,
                'rate' => $this->rate,
                'is_active' => $this->isActive,
            ]));
        }

        $this->redirect(route('admin.settings.tax.rates'), navigate: true);
    }

    public function render(): View
    {
        return view('tax::admin.rate-form');
    }
}
