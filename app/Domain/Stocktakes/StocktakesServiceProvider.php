<?php

declare(strict_types=1);

namespace App\Domain\Stocktakes;

use App\Domain\Stocktakes\Enums\StocktakeObservationType;
use App\Domain\Stocktakes\Enums\StocktakeStatus;
use App\Domain\Stocktakes\Models\Stocktake;
use App\Domain\Stocktakes\Models\StocktakeLine;
use App\Domain\Stocktakes\Models\StocktakeObservation;
use App\Domain\Stocktakes\Presentation\Livewire\Form;
use App\Domain\Stocktakes\Presentation\Livewire\Index;
use App\Domain\Stocktakes\Presentation\Livewire\Show;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class StocktakesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Preserve model identifiers and enum values serialized before extraction.
        foreach ([
            'App\\Models\\Stocktake' => Stocktake::class,
            'App\\Models\\StocktakeLine' => StocktakeLine::class,
            'App\\Models\\StocktakeObservation' => StocktakeObservation::class,
            'App\\Enums\\StocktakeStatus' => StocktakeStatus::class,
            'App\\Enums\\StocktakeObservationType' => StocktakeObservationType::class,
        ] as $legacy => $canonical) {
            if (! class_exists($legacy, false)) {
                class_alias($canonical, $legacy);
            }
        }
    }

    public function boot(): void
    {
        Relation::morphMap([
            'App\\Models\\Stocktake' => Stocktake::class,
            'App\\Models\\StocktakeLine' => StocktakeLine::class,
            'App\\Models\\StocktakeObservation' => StocktakeObservation::class,
        ]);

        $this->loadViewsFrom(__DIR__.'/Presentation/Views', 'stocktakes');
        foreach ([
            'stocktakes.index' => Index::class,
            'stocktakes.form' => Form::class,
            'stocktakes.show' => Show::class,
        ] as $name => $class) {
            Livewire::addComponent(name: $name, class: $class);
        }

        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/api.php');
    }
}
