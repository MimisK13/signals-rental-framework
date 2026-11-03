<section class="w-full min-w-0">
    <x-admin.layout group="tax" title="Finance" description="Tax-point policy and default pricing display for new opportunities.">
        <form wire:submit="save" class="space-y-8">
            <x-signals.form-section title="Tax Point">
                <div class="space-y-4">
                    <flux:select wire:model="taxPointPolicy" label="Tax Point Date">
                        <flux:select.option value="{{ \App\Domain\Tax\Enums\TaxPointPolicy::SupplyDate->value }}">Supply date (delivery / charge window)</flux:select.option>
                        <flux:select.option value="{{ \App\Domain\Tax\Enums\TaxPointPolicy::InvoiceDate->value }}">Invoice date (order date until invoicing)</flux:select.option>
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
