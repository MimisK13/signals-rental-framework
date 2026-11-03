<section class="w-full min-w-0">
    <x-admin.layout title="Company Details" description="Manage your company information, locale, and tax settings.">
        @if (session('status'))
            <x-signals.alert type="success" dismissible>{{ session('status') }}</x-signals.alert>
        @endif

        <x-signals.form-section title="Company Information">
            <form wire:submit="save" class="space-y-6">
                <flux:input wire:model="name" label="Company Name" required />

                <flux:select wire:model.live="countryCode" label="Country" required>
                    <option value="">Select country...</option>
                    @foreach(\App\Domain\ReferenceData\Data\CountryData::options() as $code => $countryName)
                        <option value="{{ $code }}">{{ $countryName }}</option>
                    @endforeach
                </flux:select>

                <div class="grid grid-cols-2 gap-4">
                    <flux:select wire:model="timezone" label="Timezone" required>
                        <option value="">Select timezone...</option>
                        @foreach(timezone_identifiers_list() as $tz)
                            <option value="{{ $tz }}">{{ $tz }}</option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="currency" label="Currency" required>
                        <option value="">Select currency...</option>
                        @foreach(['GBP','USD','EUR','CAD','AUD','NZD','CHF','SEK','NOK','DKK','ZAR','AED','SGD','JPY','INR'] as $cur)
                            <option value="{{ $cur }}">{{ $cur }}</option>
                        @endforeach
                    </flux:select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="taxRate" label="Default Tax Rate (%)" type="number" step="0.01" required />
                    <flux:input wire:model="taxLabel" label="Tax Label" placeholder="e.g. VAT, GST" required />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <flux:select wire:model="dateFormat" label="Date Format" required>
                        @foreach(['d/m/Y' => 'DD/MM/YYYY', 'm/d/Y' => 'MM/DD/YYYY', 'Y-m-d' => 'YYYY-MM-DD', 'd.m.Y' => 'DD.MM.YYYY', 'd-m-Y' => 'DD-MM-YYYY', 'Y/m/d' => 'YYYY/MM/DD'] as $fmt => $label)
                            <option value="{{ $fmt }}">{{ $label }}</option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="timeFormat" label="Time Format" required>
                        <option value="H:i">24-hour (14:30)</option>
                        <option value="g:i A">12-hour (2:30 PM)</option>
                        <option value="h:i A">12-hour (02:30 PM)</option>
                    </flux:select>
                </div>

                <flux:select wire:model="fiscalYearStart" label="Fiscal Year Start">
                    @foreach(['January','February','March','April','May','June','July','August','September','October','November','December'] as $i => $month)
                        <option value="{{ $i + 1 }}">{{ $month }}</option>
                    @endforeach
                </flux:select>

                <div class="flex items-center gap-4">
                    <flux:button variant="primary" type="submit">Save Changes</flux:button>
                </div>
            </form>
        </x-signals.form-section>
    </x-admin.layout>
</section>
