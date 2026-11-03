<?php

declare(strict_types=1);

namespace App\Domain\Settings\Presentation\Livewire\Admin;

use App\Domain\ReferenceData\Data\CountryData;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Company Details')]
final class Company extends Component
{
    public string $name = '';

    public string $countryCode = '';

    public string $timezone = '';

    public string $currency = '';

    public string $taxRate = '';

    public string $taxLabel = '';

    public string $dateFormat = '';

    public string $timeFormat = '';

    public int $fiscalYearStart = 1;

    public function mount(): void
    {
        $this->name = (string) settings('company.name', '');
        $this->countryCode = (string) settings('company.country_code', '');
        $this->timezone = (string) settings('company.timezone', '');
        $this->currency = (string) settings('company.base_currency', '');
        $this->taxRate = (string) settings('company.tax_rate', '');
        $this->taxLabel = (string) settings('company.tax_label', '');
        $this->dateFormat = (string) settings('company.date_format', '');
        $this->timeFormat = (string) settings('company.time_format', '');
        $this->fiscalYearStart = (int) settings('company.fiscal_year_start', 1);
    }

    public function updatedCountryCode(string $value): void
    {
        $defaults = CountryData::defaults($value);

        if ($defaults) {
            $this->timezone = $defaults['timezone'];
            $this->currency = $defaults['currency'];
            $this->taxRate = $defaults['tax_rate'];
            $this->taxLabel = $defaults['tax_label'];
            $this->dateFormat = $defaults['date_format'];
            $this->timeFormat = $defaults['time_format'];
        }
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'countryCode' => ['required', 'string', 'size:2'],
            'timezone' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3'],
            'taxRate' => ['required', 'numeric', 'min:0', 'max:100'],
            'taxLabel' => ['required', 'string', 'max:50'],
            'dateFormat' => ['required', 'string', 'max:20'],
            'timeFormat' => ['required', 'string', 'max:20'],
            'fiscalYearStart' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        settings()->setMany([
            'company.name' => $validated['name'],
            'company.country_code' => $validated['countryCode'],
            'company.timezone' => $validated['timezone'],
            'company.base_currency' => $validated['currency'],
            'company.tax_rate' => $validated['taxRate'],
            'company.tax_label' => $validated['taxLabel'],
            'company.date_format' => $validated['dateFormat'],
            'company.time_format' => $validated['timeFormat'],
            'company.fiscal_year_start' => ['value' => $validated['fiscalYearStart'], 'type' => 'integer'],
        ]);

        // Company name is rendered in the global header brand (outside this
        // component), so a Livewire morph cannot update it. Force a full page
        // reload; the flashed status survives the redirect to confirm the save.
        session()->flash('status', 'Company details saved.');

        $this->redirect(route('admin.settings.company'), navigate: false);
    }

    public function render(): View
    {
        return view('settings::admin.company');
    }
}
