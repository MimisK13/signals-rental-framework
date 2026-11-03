<?php

declare(strict_types=1);

namespace App\Domain\Settings\Presentation\Livewire\Admin;

use App\Domain\Settings\Services\SettingsRegistry;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Preferences')]
final class GeneralPreferences extends Component
{
    public string $numberDecimalSeparator = '.';

    public string $numberThousandsSeparator = ',';

    public string $currencyDisplay = 'symbol';

    public int $firstDayOfWeek = 1;

    public int $itemsPerPage = 25;

    public function mount(): void
    {
        $group = settings()->group('preferences');

        $this->numberDecimalSeparator = $group['number_decimal_separator'];
        $this->numberThousandsSeparator = $group['number_thousands_separator'];
        $this->currencyDisplay = $group['currency_display'];
        $this->firstDayOfWeek = (int) $group['first_day_of_week'];
        $this->itemsPerPage = (int) $group['items_per_page'];
    }

    public function save(): void
    {
        $registry = app(SettingsRegistry::class);
        $rules = $registry->rules('preferences');
        $types = $registry->types('preferences');

        $validated = $this->validate([
            'numberDecimalSeparator' => $rules['number_decimal_separator'],
            'numberThousandsSeparator' => $rules['number_thousands_separator'],
            'currencyDisplay' => $rules['currency_display'],
            'firstDayOfWeek' => $rules['first_day_of_week'],
            'itemsPerPage' => $rules['items_per_page'],
        ]);

        $settings = [
            'preferences.number_decimal_separator' => $validated['numberDecimalSeparator'],
            'preferences.number_thousands_separator' => $validated['numberThousandsSeparator'],
            'preferences.currency_display' => $validated['currencyDisplay'],
            'preferences.first_day_of_week' => ['value' => $validated['firstDayOfWeek'], 'type' => $types['first_day_of_week']],
            'preferences.items_per_page' => ['value' => $validated['itemsPerPage'], 'type' => $types['items_per_page']],
        ];

        settings()->setMany($settings);

        $this->dispatch('preferences-settings-saved');
    }

    public function render(): View
    {
        return view('settings::admin.preferences');
    }
}
