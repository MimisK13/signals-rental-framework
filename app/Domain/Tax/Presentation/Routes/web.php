<?php

declare(strict_types=1);

use App\Domain\Tax\Presentation\Livewire\Categories;
use App\Domain\Tax\Presentation\Livewire\CategoryForm;
use App\Domain\Tax\Presentation\Livewire\OrganisationTaxClasses;
use App\Domain\Tax\Presentation\Livewire\OrganisationTaxClassForm;
use App\Domain\Tax\Presentation\Livewire\ProductTaxClasses;
use App\Domain\Tax\Presentation\Livewire\ProductTaxClassForm;
use App\Domain\Tax\Presentation\Livewire\RateForm;
use App\Domain\Tax\Presentation\Livewire\Rates;
use App\Domain\Tax\Presentation\Livewire\RuleForm;
use App\Domain\Tax\Presentation\Livewire\Rules;
use App\Domain\Tax\Presentation\Livewire\ZoneForm;
use App\Domain\Tax\Presentation\Livewire\ZoneRuleForm;
use App\Domain\Tax\Presentation\Livewire\Zones;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'signals.setup-complete', 'auth', '2fa', 'admin', 'signals.session-timeout'])->group(function (): void {
    Route::livewire('admin/settings/tax/product-tax-classes', ProductTaxClasses::class)->name('admin.settings.tax.product-tax-classes');
    Route::livewire('admin/settings/tax/product-tax-classes/create', ProductTaxClassForm::class)->name('admin.settings.tax.product-tax-classes.create');
    Route::livewire('admin/settings/tax/product-tax-classes/{productTaxClass}/edit', ProductTaxClassForm::class)->name('admin.settings.tax.product-tax-classes.edit');
    Route::livewire('admin/settings/tax/organisation-tax-classes', OrganisationTaxClasses::class)->name('admin.settings.tax.organisation-tax-classes');
    Route::livewire('admin/settings/tax/organisation-tax-classes/create', OrganisationTaxClassForm::class)->name('admin.settings.tax.organisation-tax-classes.create');
    Route::livewire('admin/settings/tax/organisation-tax-classes/{organisationTaxClass}/edit', OrganisationTaxClassForm::class)->name('admin.settings.tax.organisation-tax-classes.edit');
    Route::livewire('admin/settings/tax/zones', Zones::class)->name('admin.settings.tax.zones');
    Route::livewire('admin/settings/tax/zones/create', ZoneForm::class)->name('admin.settings.tax.zones.create');
    Route::livewire('admin/settings/tax/zones/{taxZone}/edit', ZoneForm::class)->name('admin.settings.tax.zones.edit');
    Route::livewire('admin/settings/tax/categories', Categories::class)->name('admin.settings.tax.categories');
    Route::livewire('admin/settings/tax/categories/create', CategoryForm::class)->name('admin.settings.tax.categories.create');
    Route::livewire('admin/settings/tax/categories/{taxCategory}/edit', CategoryForm::class)->name('admin.settings.tax.categories.edit');
    Route::livewire('admin/settings/tax/rates', Rates::class)->name('admin.settings.tax.rates');
    Route::livewire('admin/settings/tax/rates/create', RateForm::class)->name('admin.settings.tax.rates.create');
    Route::livewire('admin/settings/tax/rates/{taxRate}/edit', RateForm::class)->name('admin.settings.tax.rates.edit');
    Route::livewire('admin/settings/tax/rules', Rules::class)->name('admin.settings.tax.rules');
    Route::livewire('admin/settings/tax/rules/create', RuleForm::class)->name('admin.settings.tax.rules.create');
    Route::livewire('admin/settings/tax/rules/create-zone', ZoneRuleForm::class)->name('admin.settings.tax.rules.create-zone');
    Route::livewire('admin/settings/tax/rules/{taxRule}/edit', RuleForm::class)->name('admin.settings.tax.rules.edit');
    Route::livewire('admin/settings/tax/rules/{taxRule}/edit-zone', ZoneRuleForm::class)->name('admin.settings.tax.rules.edit-zone');
});
