<?php

declare(strict_types=1);

namespace App\Domain\Tax;

use App\Domain\Tax\Console\RevalidateExpiredVatNumbers;
use App\Domain\Tax\Contracts\TaxResolver;
use App\Domain\Tax\Contracts\VatNumberValidator;
use App\Domain\Tax\Models\OrganisationTaxClass;
use App\Domain\Tax\Models\ProductTaxClass;
use App\Domain\Tax\Models\TaxCategory;
use App\Domain\Tax\Models\TaxZone;
use App\Domain\Tax\Policies\OrganisationTaxClassPolicy;
use App\Domain\Tax\Policies\ProductTaxClassPolicy;
use App\Domain\Tax\Policies\TaxCategoryPolicy;
use App\Domain\Tax\Policies\TaxZonePolicy;
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
use App\Domain\Tax\Services\DefaultVatNumberValidator;
use App\Domain\Tax\Services\TaxCalculator;
use App\Domain\Tax\Services\TaxResolutionContextHolder;
use App\Domain\Tax\Services\TaxZoneRuleResolver;
use App\Domain\Tax\Services\ZoneAwareTaxResolver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class TaxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TaxCalculator::class);
        $this->app->scoped(TaxResolutionContextHolder::class);
        $this->app->singleton(TaxZoneRuleResolver::class);
        $this->app->singleton(ZoneAwareTaxResolver::class);
        $this->app->bind(TaxResolver::class, ZoneAwareTaxResolver::class);
        $this->app->singleton(VatNumberValidator::class, DefaultVatNumberValidator::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/Presentation/Views', 'tax');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/api.php');

        Gate::policy(OrganisationTaxClass::class, OrganisationTaxClassPolicy::class);
        Gate::policy(ProductTaxClass::class, ProductTaxClassPolicy::class);
        Gate::policy(TaxCategory::class, TaxCategoryPolicy::class);
        Gate::policy(TaxZone::class, TaxZonePolicy::class);

        $this->commands([RevalidateExpiredVatNumbers::class]);

        Livewire::addComponent(name: 'admin.settings.tax.categories', class: Categories::class);
        Livewire::addComponent(name: 'admin.settings.tax.category-form', class: CategoryForm::class);
        Livewire::addComponent(name: 'admin.settings.tax.organisation-tax-class-form', class: OrganisationTaxClassForm::class);
        Livewire::addComponent(name: 'admin.settings.tax.organisation-tax-classes', class: OrganisationTaxClasses::class);
        Livewire::addComponent(name: 'admin.settings.tax.product-tax-class-form', class: ProductTaxClassForm::class);
        Livewire::addComponent(name: 'admin.settings.tax.product-tax-classes', class: ProductTaxClasses::class);
        Livewire::addComponent(name: 'admin.settings.tax.rate-form', class: RateForm::class);
        Livewire::addComponent(name: 'admin.settings.tax.rates', class: Rates::class);
        Livewire::addComponent(name: 'admin.settings.tax.rule-form', class: RuleForm::class);
        Livewire::addComponent(name: 'admin.settings.tax.rules', class: Rules::class);
        Livewire::addComponent(name: 'admin.settings.tax.zone-form', class: ZoneForm::class);
        Livewire::addComponent(name: 'admin.settings.tax.zone-rule-form', class: ZoneRuleForm::class);
        Livewire::addComponent(name: 'admin.settings.tax.zones', class: Zones::class);
    }
}
