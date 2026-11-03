<?php

use App\Domain\Administration\Models\User;
use App\Domain\Settings\Presentation\Livewire\Admin\GeneralPreferences;
use Livewire\Livewire;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->user = User::factory()->admin()->create();
    $this->actingAs($this->user);
});

it('renders the general preferences page', function () {
    $this->get(route('admin.settings.preferences'))
        ->assertOk()
        ->assertSee('General');
});

it('loads general preferences with defaults', function () {
    Livewire::test(GeneralPreferences::class)
        ->assertSet('numberDecimalSeparator', '.')
        ->assertSet('numberThousandsSeparator', ',')
        ->assertSet('currencyDisplay', 'symbol')
        ->assertSet('firstDayOfWeek', 1)
        ->assertSet('itemsPerPage', 25);
});

it('saves general preferences', function () {
    Livewire::test(GeneralPreferences::class)
        ->set('numberDecimalSeparator', ',')
        ->set('numberThousandsSeparator', '.')
        ->set('currencyDisplay', 'code')
        ->set('firstDayOfWeek', 0)
        ->set('itemsPerPage', 50)
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('preferences-settings-saved');

    expect(settings('preferences.number_decimal_separator'))->toBe(',');
    expect(settings('preferences.number_thousands_separator'))->toBe('.');
    expect(settings('preferences.currency_display'))->toBe('code');
    expect(settings('preferences.first_day_of_week'))->toBe(0);
    expect(settings('preferences.items_per_page'))->toBe(50);
});

it('validates items per page options', function () {
    Livewire::test(GeneralPreferences::class)
        ->set('itemsPerPage', 999)
        ->call('save')
        ->assertHasErrors(['itemsPerPage']);
});

it('validates currency display options', function () {
    Livewire::test(GeneralPreferences::class)
        ->set('currencyDisplay', 'invalid')
        ->call('save')
        ->assertHasErrors(['currencyDisplay']);
});

it('returns 403 for non-admin users', function () {
    $regularUser = User::factory()->create();

    $this->actingAs($regularUser)
        ->get(route('admin.settings.preferences'))
        ->assertForbidden();
});
