<?php

use App\Enums\TaxPointPolicy;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->user = User::factory()->admin()->create();
    $this->actingAs($this->user);
});

it('renders the finance settings page', function () {
    $this->get(route('admin.settings.finance'))
        ->assertOk()
        ->assertSee('Finance');
});

it('loads finance settings with defaults', function () {
    Volt::test('admin.settings.finance')
        ->assertSet('taxPointPolicy', TaxPointPolicy::SupplyDate->value)
        ->assertSet('pricesIncludeTaxDefault', false)
        ->assertSet('showTaxOnDocuments', true);
});

it('saves finance settings', function () {
    Volt::test('admin.settings.finance')
        ->set('taxPointPolicy', TaxPointPolicy::InvoiceDate->value)
        ->set('pricesIncludeTaxDefault', true)
        ->set('showTaxOnDocuments', false)
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('finance-settings-saved');

    expect(settings('finance.tax_point_policy'))->toBe('invoice_date')
        ->and(settings('finance.prices_include_tax_default'))->toBeTrue()
        ->and(settings('finance.show_tax_on_documents'))->toBeFalse();
});

it('validates tax_point_policy options', function () {
    Volt::test('admin.settings.finance')
        ->set('taxPointPolicy', 'invalid')
        ->call('save')
        ->assertHasErrors(['taxPointPolicy']);
});

it('returns 403 for non-admin users', function () {
    $regularUser = User::factory()->create();

    $this->actingAs($regularUser)
        ->get(route('admin.settings.finance'))
        ->assertForbidden();
});
