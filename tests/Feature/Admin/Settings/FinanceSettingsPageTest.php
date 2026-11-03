<?php

use App\Domain\Administration\Models\User;
use App\Domain\Settings\Presentation\Livewire\Admin\Finance;
use App\Domain\Tax\Enums\TaxPointPolicy;
use Livewire\Livewire;

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
    Livewire::test(Finance::class)
        ->assertSet('taxPointPolicy', TaxPointPolicy::SupplyDate->value)
        ->assertSet('pricesIncludeTaxDefault', false)
        ->assertSet('showTaxOnDocuments', true)
        ->assertSet('vatValidationValidityDays', 30)
        ->assertSet('reverseChargeLabel', 'Reverse charge');
});

it('saves finance settings', function () {
    Livewire::test(Finance::class)
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

it('saves vat validation validity days', function () {
    Livewire::test(Finance::class)
        ->set('vatValidationValidityDays', 14)
        ->call('save')
        ->assertHasNoErrors();

    expect(settings('finance.vat_validation_validity_days'))->toBe(14);
});

it('saves reverse charge label', function () {
    Livewire::test(Finance::class)
        ->set('reverseChargeLabel', 'Customer VAT due')
        ->call('save')
        ->assertHasNoErrors();

    expect(settings('finance.reverse_charge_label'))->toBe('Customer VAT due');
});

it('validates tax_point_policy options', function () {
    Livewire::test(Finance::class)
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
