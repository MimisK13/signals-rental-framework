<?php

use App\Enums\TaxPointPolicy;
use App\Services\SettingsRegistry;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Finance')] class extends Component {
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
}; ?>

<section class="w-full">
    <x-admin.layout group="tax" title="Finance" description="Tax-point policy and default pricing display for new opportunities.">
        <form wire:submit="save" class="space-y-8">
            <x-signals.form-section title="Tax Point">
                <div class="space-y-4">
                    <flux:select wire:model="taxPointPolicy" label="Tax Point Date">
                        <flux:select.option value="{{ TaxPointPolicy::SupplyDate->value }}">Supply date (delivery / charge window)</flux:select.option>
                        <flux:select.option value="{{ TaxPointPolicy::InvoiceDate->value }}">Invoice date (order date until invoicing)</flux:select.option>
                    </flux:select>
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                        Determines which date is passed to tax rate effective-dating when opportunities are priced.
                    </flux:text>
                </div>
            </x-signals.form-section>

            <x-signals.form-section title="Pricing Defaults">
                <div class="space-y-4">
                    <flux:switch wire:model="pricesIncludeTaxDefault" label="New opportunities default to prices include tax" />
                    <flux:switch wire:model="showTaxOnDocuments" label="Show tax breakdown on documents" />
                </div>
            </x-signals.form-section>

            <x-signals.form-section title="VAT Validation">
                <div class="space-y-4">
                    <flux:input
                        wire:model="vatValidationValidityDays"
                        type="number"
                        min="0"
                        max="3650"
                        label="VAT validation validity (days)"
                    />
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                        How long a successful VIES/HMRC check remains trusted for reverse-charge eligibility. Set to 0 to never expire.
                    </flux:text>
                </div>
            </x-signals.form-section>

            <x-signals.form-section title="Reverse Charge Display">
                <div class="space-y-4">
                    <flux:input wire:model="reverseChargeLabel" label="Reverse charge label" maxlength="255" />
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                        Display name shown on tax breakdowns and documents when reverse charge applies.
                    </flux:text>
                </div>
            </x-signals.form-section>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">Save Changes</flux:button>

                <x-action-message on="finance-settings-saved">
                    Saved.
                </x-action-message>
            </div>
        </form>
    </x-admin.layout>
</section>
