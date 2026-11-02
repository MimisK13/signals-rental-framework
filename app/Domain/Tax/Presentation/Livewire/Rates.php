<?php

declare(strict_types=1);

namespace App\Domain\Tax\Presentation\Livewire;

use App\Domain\Tax\Actions\DeleteTaxRate;
use App\Domain\Tax\Models\TaxRate;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')] #[Title('Tax Rates')]
final class Rates extends Component
{
    /** @var Collection<int, TaxRate> */
    public $taxRates;

    public function mount(): void
    {
        $this->loadTaxRates();
    }

    public function loadTaxRates(): void
    {
        $this->taxRates = TaxRate::query()->orderBy('name')->get();
    }

    public function deleteTaxRate(int $taxRateId): void
    {
        $taxRate = TaxRate::findOrFail($taxRateId);

        if ($taxRate->taxRules()->exists()) {
            $this->addError('deleteTaxRate', 'This tax rate is used by one or more tax rules and cannot be deleted.');

            return;
        }

        (new DeleteTaxRate)($taxRate);
        $this->loadTaxRates();
    }

    public function render(): View
    {
        return view('tax::admin.rates');
    }
}
