<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

use App\Domain\Pricing\Models\BundleRule;
use App\Domain\Pricing\Models\DiscountCategory;
use App\Domain\Pricing\Models\DiscountRule;
use App\Domain\Pricing\Models\PriceCategory;
use App\Domain\Pricing\Models\Surcharge;
use App\Domain\Pricing\Models\SurchargeGroup;
use App\Domain\Pricing\Policies\BundleRulePolicy;
use App\Domain\Pricing\Policies\DiscountCategoryPolicy;
use App\Domain\Pricing\Policies\DiscountRulePolicy;
use App\Domain\Pricing\Policies\PriceCategoryPolicy;
use App\Domain\Pricing\Policies\SurchargeGroupPolicy;
use App\Domain\Pricing\Policies\SurchargePolicy;
use App\Domain\Pricing\Presentation\Livewire\BundleRuleForm;
use App\Domain\Pricing\Presentation\Livewire\BundleRules;
use App\Domain\Pricing\Presentation\Livewire\DiscountCategories;
use App\Domain\Pricing\Presentation\Livewire\DiscountCategoryForm;
use App\Domain\Pricing\Presentation\Livewire\PriceCategories;
use App\Domain\Pricing\Presentation\Livewire\PriceCategoryForm;
use App\Domain\Pricing\Presentation\Livewire\SurchargeGroupForm;
use App\Domain\Pricing\Presentation\Livewire\SurchargeGroups;
use App\Domain\Pricing\Services\BundlePricingApplicator;
use App\Domain\Pricing\Services\CriteriaMatcher;
use App\Domain\Pricing\Services\DealPriceProportionalDistributor;
use App\Domain\Pricing\Services\DealPriceValidatorRegistry;
use App\Domain\Pricing\Services\DiscountCategoryApplier;
use App\Domain\Pricing\Services\DiscountCriteriaPredicateRegistry;
use App\Domain\Pricing\Services\DiscountRuleEvaluator;
use App\Domain\Pricing\Services\DiscountSourceRegistry;
use App\Domain\Pricing\Services\OpportunityItemDiscountPercentFilterRegistry;
use App\Domain\Pricing\Services\OpportunityItemSurchargeAmountFilterRegistry;
use App\Domain\Pricing\Services\OpportunityPricingCalculateDecorator;
use App\Domain\Pricing\Services\OpportunityPricingRecalculator;
use App\Domain\Pricing\Services\Predicates\ProductGroupIdsPredicate;
use App\Domain\Pricing\Services\Predicates\ProductIdsPredicate;
use App\Domain\Pricing\Services\Predicates\QuantityMaxPredicate;
use App\Domain\Pricing\Services\Predicates\QuantityMinPredicate;
use App\Domain\Pricing\Services\Predicates\TagNamesPredicate;
use App\Domain\Pricing\Services\Predicates\TransactionTypesPredicate;
use App\Domain\Pricing\Services\QuantityContextResolver;
use App\Domain\Pricing\Settings\PricingSettings;
use App\Domain\Projects\Opportunities\Services\Hooks\WorkflowTriggerRegistry;
use App\Services\SettingsRegistry;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class PricingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->afterResolving(SettingsRegistry::class, function (SettingsRegistry $registry): void {
            $registry->register(new PricingSettings);
        });

        $this->app->singleton(DiscountCriteriaPredicateRegistry::class, function (): DiscountCriteriaPredicateRegistry {
            $registry = new DiscountCriteriaPredicateRegistry;
            $registry->register(new ProductGroupIdsPredicate);
            $registry->register(new QuantityMinPredicate);
            $registry->register(new QuantityMaxPredicate);
            $registry->register(new ProductIdsPredicate);
            $registry->register(new TagNamesPredicate);
            $registry->register(new TransactionTypesPredicate);

            return $registry;
        });

        foreach ([
            CriteriaMatcher::class,
            BundlePricingApplicator::class,
            QuantityContextResolver::class,
            DiscountRuleEvaluator::class,
            DiscountCategoryApplier::class,
            DealPriceValidatorRegistry::class,
            DiscountSourceRegistry::class,
            OpportunityItemDiscountPercentFilterRegistry::class,
            OpportunityItemSurchargeAmountFilterRegistry::class,
            OpportunityPricingCalculateDecorator::class,
            DealPriceProportionalDistributor::class,
            OpportunityPricingRecalculator::class,
        ] as $service) {
            $this->app->singleton($service);
        }
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/Presentation/Views', 'pricing');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/api.php');

        foreach ([
            BundleRule::class => BundleRulePolicy::class,
            DiscountCategory::class => DiscountCategoryPolicy::class,
            DiscountRule::class => DiscountRulePolicy::class,
            PriceCategory::class => PriceCategoryPolicy::class,
            Surcharge::class => SurchargePolicy::class,
            SurchargeGroup::class => SurchargeGroupPolicy::class,
        ] as $model => $policy) {
            Gate::policy($model, $policy);
        }

        foreach ([
            'admin.settings.pricing.price-categories' => PriceCategories::class,
            'admin.settings.pricing.price-category-form' => PriceCategoryForm::class,
            'admin.settings.pricing.discount-categories' => DiscountCategories::class,
            'admin.settings.pricing.discount-category-form' => DiscountCategoryForm::class,
            'admin.settings.pricing.bundle-rules' => BundleRules::class,
            'admin.settings.pricing.bundle-rule-form' => BundleRuleForm::class,
            'admin.settings.pricing.surcharge-groups' => SurchargeGroups::class,
            'admin.settings.pricing.surcharge-group-form' => SurchargeGroupForm::class,
        ] as $name => $class) {
            Livewire::addComponent(name: $name, class: $class);
        }

        $registry = app(WorkflowTriggerRegistry::class);
        foreach ([
            'opportunity.deal_price_set',
            'opportunity.deal_price_cleared',
            'opportunity.group_deal_price_set',
            'opportunity.group_deal_price_cleared',
            'opportunity.pricing_recalculated',
            'opportunity.discount_reset',
        ] as $transition) {
            $registry->register($transition, 'core-pricing-seam');
        }
    }
}
