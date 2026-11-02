<?php

declare(strict_types=1);

namespace App\Domain\Rates;

use App\Domain\Rates\Models\ProductRate;
use App\Domain\Rates\Models\RateDefinition;
use App\Domain\Rates\Presentation\Livewire\ProductRateForm;
use App\Domain\Rates\Presentation\Livewire\ProductRates;
use App\Domain\Rates\Presentation\Livewire\RateDefinitionForm;
use App\Domain\Rates\Presentation\Livewire\RateDefinitions;
use App\Domain\Rates\Services\RateEngine\Modifiers\FactorModifier;
use App\Domain\Rates\Services\RateEngine\Modifiers\MultiplierModifier;
use App\Domain\Rates\Services\RateEngine\RateEngineRegistry;
use App\Domain\Rates\Services\RateEngine\RateResolver;
use App\Domain\Rates\Services\RateEngine\Strategies\FixedStrategy;
use App\Domain\Rates\Services\RateEngine\Strategies\HybridStrategy;
use App\Domain\Rates\Services\RateEngine\Strategies\PeriodStrategy;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class RatesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Auditable events persisted these model identifiers before extraction.
        foreach ([
            'App\\Models\\RateDefinition' => RateDefinition::class,
            'App\\Models\\ProductRate' => ProductRate::class,
        ] as $legacy => $canonical) {
            if (! class_exists($legacy, false)) {
                class_alias($canonical, $legacy);
            }
        }

        $this->app->scoped(RateResolver::class);

        $this->app->singleton(RateEngineRegistry::class, function (): RateEngineRegistry {
            $registry = new RateEngineRegistry;
            $registry->registerStrategy(new PeriodStrategy);
            $registry->registerStrategy(new FixedStrategy);
            $registry->registerStrategy(new HybridStrategy);
            $registry->registerModifier(new MultiplierModifier);
            $registry->registerModifier(new FactorModifier);

            return $registry;
        });
    }

    public function boot(): void
    {
        Relation::morphMap([
            'App\\Models\\RateDefinition' => RateDefinition::class,
            'App\\Models\\ProductRate' => ProductRate::class,
        ]);

        $this->loadViewsFrom(__DIR__.'/Presentation/Views', 'rates');
        foreach ([
            'products.rates' => ProductRates::class,
            'products.rate-form' => ProductRateForm::class,
            'admin.settings.rate-definitions' => RateDefinitions::class,
            'admin.settings.rate-definition-form' => RateDefinitionForm::class,
        ] as $name => $class) {
            Livewire::addComponent(name: $name, class: $class);
        }

        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/api.php');
    }
}
