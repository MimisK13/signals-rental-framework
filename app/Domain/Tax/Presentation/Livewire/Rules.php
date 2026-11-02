<?php

declare(strict_types=1);

namespace App\Domain\Tax\Presentation\Livewire;

use App\Domain\Tax\Actions\DeleteTaxRule;
use App\Domain\Tax\Models\TaxRule;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')] #[Title('Tax Rules')]
final class Rules extends Component
{
    /** @var Collection<int, TaxRule> */
    public $taxRules;

    public function mount(): void
    {
        $this->loadTaxRules();
    }

    public function loadTaxRules(): void
    {
        $this->taxRules = TaxRule::query()
            ->with(['organisationTaxClass', 'productTaxClass', 'taxRate', 'sellerZone', 'buyerZone', 'taxCategory'])
            ->orderByDesc('priority')
            ->get();
    }

    public function deleteTaxRule(int $taxRuleId): void
    {
        $taxRule = TaxRule::findOrFail($taxRuleId);
        (new DeleteTaxRule)($taxRule);
        $this->loadTaxRules();
    }

    public function render(): View
    {
        return view('tax::admin.rules');
    }
}
