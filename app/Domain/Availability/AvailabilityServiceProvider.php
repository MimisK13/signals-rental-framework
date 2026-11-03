<?php

declare(strict_types=1);

namespace App\Domain\Availability;

use App\Domain\Availability\Console\DetectOverdueDemands;
use App\Domain\Availability\Contracts\AvailabilityDataPresence;
use App\Domain\Availability\Contracts\AvailabilityResolutionProvider;
use App\Domain\Availability\Contracts\AvailabilityStrategyContract;
use App\Domain\Availability\Events\AvailabilityChanged;
use App\Domain\Availability\Presentation\Livewire\Bookings;
use App\Domain\Availability\Presentation\Livewire\Index;
use App\Domain\Availability\Presentation\Livewire\ProductAvailability;
use App\Domain\Availability\Services\AvailabilityService;
use App\Domain\Availability\Services\ContainerDemandResolver;
use App\Domain\Availability\Services\DatabaseAvailabilityDataPresence;
use App\Domain\Availability\Services\DemandSourceDefinition;
use App\Domain\Availability\Services\DemandSourceRegistry;
use App\Domain\Availability\Services\InspectionHoldDemandResolver;
use App\Domain\Availability\Services\KitAvailabilityCalculator;
use App\Domain\Availability\Services\OpportunityItemDemandResolver;
use App\Domain\Availability\Services\PassThroughAvailabilityStrategy;
use App\Domain\Availability\Services\RecalculationPipeline;
use App\Domain\Availability\Services\RepairDemandResolver;
use App\Domain\Availability\Services\SettingsAvailabilityResolutionProvider;
use App\Domain\Availability\Services\SlotCalculator;
use App\Domain\Availability\Services\VirtualStockDemandResolver;
use App\Domain\Availability\Settings\AvailabilitySettings;
use App\Domain\Availability\Shortages\Console\EscalateUnresolvedShortages;
use App\Domain\Availability\Shortages\Listeners\DetectOrderShortages;
use App\Domain\Availability\Shortages\Listeners\MatchWaitlistMonitors;
use App\Domain\Availability\Shortages\Presentation\Livewire\OpportunityShortages;
use App\Domain\Availability\Shortages\Services\CostApportionmentRegistry;
use App\Domain\Availability\Shortages\Services\NullCostApportionmentStrategy;
use App\Domain\Availability\Shortages\Services\PipelineShortageEmitter;
use App\Domain\Availability\Shortages\Services\Resolvers\DateShiftResolver;
use App\Domain\Availability\Shortages\Services\Resolvers\PartialFulfilmentResolver;
use App\Domain\Availability\Shortages\Services\Resolvers\QuoteReallocationResolver;
use App\Domain\Availability\Shortages\Services\Resolvers\SubHireResolver;
use App\Domain\Availability\Shortages\Services\Resolvers\SubstitutionResolver;
use App\Domain\Availability\Shortages\Services\Resolvers\WaitlistResolver;
use App\Domain\Availability\Shortages\Services\Resolvers\WarehouseTransferResolver;
use App\Domain\Availability\Shortages\Services\ShortageDetector;
use App\Domain\Availability\Shortages\Services\ShortageResolverDefinition;
use App\Domain\Availability\Shortages\Services\ShortageResolverRegistry;
use App\Domain\Availability\Shortages\Services\Strategies\EvenSplitCostApportionmentStrategy;
use App\Domain\Availability\Shortages\Services\Strategies\ManualCostApportionmentStrategy;
use App\Domain\Availability\Shortages\Services\Strategies\PrimaryJobCostApportionmentStrategy;
use App\Domain\Availability\Shortages\Services\Strategies\ProportionalQuantityCostApportionmentStrategy;
use App\Domain\Availability\Shortages\Services\Strategies\ProportionalQuantityDurationCostApportionmentStrategy;
use App\Domain\Availability\Shortages\Services\SubHireSupplierCostQuery;
use App\Domain\Settings\Services\SettingsRegistry;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class AvailabilityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AvailabilityDataPresence::class, DatabaseAvailabilityDataPresence::class);
        $this->app->bind(AvailabilityResolutionProvider::class, SettingsAvailabilityResolutionProvider::class);
        $this->app->bind(AvailabilityStrategyContract::class, PassThroughAvailabilityStrategy::class);

        $this->app->singleton(SlotCalculator::class);
        $this->app->singleton(PipelineShortageEmitter::class);
        $this->app->scoped(RecalculationPipeline::class);
        $this->app->scoped(ShortageDetector::class);
        $this->app->scoped(AvailabilityService::class);

        $this->app->singleton(KitAvailabilityCalculator::class, function ($app): KitAvailabilityCalculator {
            return new KitAvailabilityCalculator(function () use ($app): AvailabilityService {
                return $app->make(AvailabilityService::class);
            });
        });

        $this->app->singleton(DemandSourceRegistry::class, function (): DemandSourceRegistry {
            $registry = new DemandSourceRegistry;
            $registry->register(new DemandSourceDefinition('opportunity_item', 'Bookings', OpportunityItemDemandResolver::class, '#3B82F6', 'calendar'));
            $registry->register(new DemandSourceDefinition('container', 'Containers', ContainerDemandResolver::class, '#0EA5E9', 'cube'));
            $registry->register(new DemandSourceDefinition('virtual_stock_intake', 'Virtual Stock', VirtualStockDemandResolver::class, '#8B5CF6', 'arrow-down-circle'));
            $registry->register(new DemandSourceDefinition('repair', 'Repairs', RepairDemandResolver::class, '#F59E0B', 'wrench'));
            $registry->register(new DemandSourceDefinition('inspection_hold', 'Return inspection holds', InspectionHoldDemandResolver::class, '#64748B', 'clipboard-check'));

            return $registry;
        });

        $this->app->scoped(QuoteReallocationResolver::class);
        $this->app->scoped(SubstitutionResolver::class);
        $this->app->scoped(WarehouseTransferResolver::class);
        $this->app->scoped(SubHireSupplierCostQuery::class);

        $this->app->singleton(ShortageResolverRegistry::class, function (): ShortageResolverRegistry {
            $registry = new ShortageResolverRegistry;
            $registry->register(new ShortageResolverDefinition('reallocate', QuoteReallocationResolver::class));
            $registry->register(new ShortageResolverDefinition('substitute', SubstitutionResolver::class));
            $registry->register(new ShortageResolverDefinition('transfer', WarehouseTransferResolver::class));
            $registry->register(new ShortageResolverDefinition('date_shift', DateShiftResolver::class));
            $registry->register(new ShortageResolverDefinition('partial', PartialFulfilmentResolver::class));
            $registry->register(new ShortageResolverDefinition('waitlist', WaitlistResolver::class));
            $registry->register(new ShortageResolverDefinition('subhire', SubHireResolver::class));

            return $registry;
        });

        $this->app->singleton(CostApportionmentRegistry::class, function ($app): CostApportionmentRegistry {
            $registry = new CostApportionmentRegistry;
            $registry->register(new NullCostApportionmentStrategy);
            $registry->register($app->make(PrimaryJobCostApportionmentStrategy::class));
            $registry->register($app->make(EvenSplitCostApportionmentStrategy::class));
            $registry->register($app->make(ProportionalQuantityCostApportionmentStrategy::class));
            $registry->register($app->make(ProportionalQuantityDurationCostApportionmentStrategy::class));
            $registry->register($app->make(ManualCostApportionmentStrategy::class));

            return $registry;
        });

        $this->app->afterResolving(SettingsRegistry::class, function (SettingsRegistry $registry): void {
            $registry->register(new AvailabilitySettings);
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadMigrationsFrom(__DIR__.'/Shortages/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/Presentation/Views', 'availability');
        $this->loadViewsFrom(__DIR__.'/Shortages/Presentation/Views', 'shortages');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/api.php');

        Livewire::addComponent(name: 'availability.index', class: Index::class);
        Livewire::addComponent(name: 'availability.bookings', class: Bookings::class);
        Livewire::addComponent(name: 'products.availability', class: ProductAvailability::class);
        Livewire::addComponent(name: 'opportunities.shortages', class: OpportunityShortages::class);

        Event::listen(AvailabilityChanged::class, DetectOrderShortages::class);
        Event::listen(AvailabilityChanged::class, MatchWaitlistMonitors::class);

        $this->commands([
            DetectOverdueDemands::class,
            EscalateUnresolvedShortages::class,
        ]);
    }
}
