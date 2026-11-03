<section class="w-full min-w-0">
    <x-admin.layout group="preferences" title="General" description="Configure number formatting and display preferences.">
        <form wire:submit="save" class="space-y-8">
            <x-signals.form-section title="Formatting">
                <div class="space-y-4">
                    <flux:select wire:model="firstDayOfWeek" label="First Day of Week">
                        <flux:select.option value="0">Sunday</flux:select.option>
                        <flux:select.option value="1">Monday</flux:select.option>
                        <flux:select.option value="6">Saturday</flux:select.option>
                    </flux:select>
                </div>
            </x-signals.form-section>

            <x-signals.form-section title="Number Formatting">
                <div class="grid grid-cols-2 gap-4">
                    <flux:select wire:model="numberDecimalSeparator" label="Decimal Separator">
                        <flux:select.option value=".">Period (.)</flux:select.option>
                        <flux:select.option value=",">Comma (,)</flux:select.option>
                    </flux:select>

                    <flux:select wire:model="numberThousandsSeparator" label="Thousands Separator">
                        <flux:select.option value=",">Comma (1,000)</flux:select.option>
                        <flux:select.option value=".">Period (1.000)</flux:select.option>
                        <flux:select.option value=" ">Space (1 000)</flux:select.option>
                        <flux:select.option value="">None (1000)</flux:select.option>
                    </flux:select>
                </div>
            </x-signals.form-section>

            <x-signals.form-section title="Display">
                <div class="grid grid-cols-2 gap-4">
                    <flux:select wire:model="currencyDisplay" label="Currency Display">
                        <flux:select.option value="symbol">Symbol ($, &pound;, &euro;)</flux:select.option>
                        <flux:select.option value="code">Code (USD, GBP, EUR)</flux:select.option>
                        <flux:select.option value="name">Name (US Dollar)</flux:select.option>
                    </flux:select>

                    <flux:select wire:model="itemsPerPage" label="Items Per Page">
                        <flux:select.option value="10">10</flux:select.option>
                        <flux:select.option value="25">25</flux:select.option>
                        <flux:select.option value="50">50</flux:select.option>
                        <flux:select.option value="100">100</flux:select.option>
                    </flux:select>
                </div>
            </x-signals.form-section>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">Save Changes</flux:button>

                <x-action-message on="preferences-settings-saved">
                    Saved.
                </x-action-message>
            </div>
        </form>
    </x-admin.layout>
</section>
