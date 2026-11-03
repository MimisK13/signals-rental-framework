<?php

declare(strict_types=1);

namespace App\Domain\Settings\Presentation\Livewire\Admin;

use App\Domain\Settings\Services\SettingsRegistry;
use App\Domain\Tax\Enums\TaxPointPolicy;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Finance')]
final class Finance extends Component
{
    public string $taxPointPolicy = TaxPointPolicy::SupplyDate->value;

    public bool $pricesIncludeTaxDefault = false;

    public bool $showTaxOnDocuments = true;

    public ?int $vatValidationValidityDays = 30;

    public string $reverseChargeLabel = 'Reverse charge';

    public function mount(): void
    {
        $group = settings()->group('finance');

        $this->taxPointPolicy = $group['tax_point_policy'];
        $this->pricesIncludeTaxDefault = (bool) $group['prices_include_tax_default'];
        $this->showTaxOnDocuments = (bool) $group['show_tax_on_documents'];
        $this->vatValidationValidityDays = isset($group['vat_validation_validity_days'])
            ? (int) $group['vat_validation_validity_days']
            : 30;
        $this->reverseChargeLabel = (string) ($group['reverse_charge_label'] ?? 'Reverse charge');
    }

    public function save(): void
    {
        $registry = app(SettingsRegistry::class);
        $rules = $registry->rules('finance');
        $types = $registry->types('finance');

        $validated = $this->validate([
            'taxPointPolicy' => $rules['tax_point_policy'],
            'pricesIncludeTaxDefault' => $rules['prices_include_tax_default'],
            'showTaxOnDocuments' => $rules['show_tax_on_documents'],
            'vatValidationValidityDays' => $rules['vat_validation_validity_days'],
            'reverseChargeLabel' => $rules['reverse_charge_label'],
        ]);

        settings()->setMany([
            'finance.tax_point_policy' => $validated['taxPointPolicy'],
            'finance.prices_include_tax_default' => [
                'value' => $validated['pricesIncludeTaxDefault'],
                'type' => $types['prices_include_tax_default'],
            ],
            'finance.show_tax_on_documents' => [
                'value' => $validated['showTaxOnDocuments'],
                'type' => $types['show_tax_on_documents'],
            ],
            'finance.vat_validation_validity_days' => [
                'value' => $validated['vatValidationValidityDays'],
                'type' => $types['vat_validation_validity_days'],
            ],
            'finance.reverse_charge_label' => $validated['reverseChargeLabel'],
        ]);

        $this->dispatch('finance-settings-saved');
    }

    public function render(): View
    {
        return view('settings::admin.finance');
    }
}
