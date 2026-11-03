<?php

declare(strict_types=1);

use App\Domain\Pricing\Presentation\Livewire\BundleRuleForm;
use App\Domain\Pricing\Presentation\Livewire\BundleRules;
use App\Domain\Pricing\Presentation\Livewire\DiscountCategories;
use App\Domain\Pricing\Presentation\Livewire\DiscountCategoryForm;
use App\Domain\Pricing\Presentation\Livewire\PriceCategories;
use App\Domain\Pricing\Presentation\Livewire\PriceCategoryForm;
use App\Domain\Pricing\Presentation\Livewire\SurchargeGroupForm;
use App\Domain\Pricing\Presentation\Livewire\SurchargeGroups;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'signals.setup-complete', 'auth', '2fa', 'admin', 'signals.session-timeout'])->group(function (): void {
    Route::livewire('admin/settings/pricing/discount-categories', DiscountCategories::class)->name('admin.settings.pricing.discount-categories');
    Route::livewire('admin/settings/pricing/discount-categories/create', DiscountCategoryForm::class)->name('admin.settings.pricing.discount-categories.create');
    Route::livewire('admin/settings/pricing/discount-categories/{discountCategory}/edit', DiscountCategoryForm::class)->name('admin.settings.pricing.discount-categories.edit');
    Route::livewire('admin/settings/pricing/price-categories', PriceCategories::class)->name('admin.settings.pricing.price-categories');
    Route::livewire('admin/settings/pricing/price-categories/create', PriceCategoryForm::class)->name('admin.settings.pricing.price-categories.create');
    Route::livewire('admin/settings/pricing/price-categories/{priceCategory}/edit', PriceCategoryForm::class)->name('admin.settings.pricing.price-categories.edit');
    Route::livewire('admin/settings/pricing/bundle-rules', BundleRules::class)->name('admin.settings.pricing.bundle-rules');
    Route::livewire('admin/settings/pricing/bundle-rules/create', BundleRuleForm::class)->name('admin.settings.pricing.bundle-rules.create');
    Route::livewire('admin/settings/pricing/bundle-rules/{bundleRule}/edit', BundleRuleForm::class)->name('admin.settings.pricing.bundle-rules.edit');
    Route::livewire('admin/settings/pricing/surcharge-groups', SurchargeGroups::class)->name('admin.settings.pricing.surcharge-groups');
    Route::livewire('admin/settings/pricing/surcharge-groups/create', SurchargeGroupForm::class)->name('admin.settings.pricing.surcharge-groups.create');
    Route::livewire('admin/settings/pricing/surcharge-groups/{surchargeGroup}/edit', SurchargeGroupForm::class)->name('admin.settings.pricing.surcharge-groups.edit');
});
