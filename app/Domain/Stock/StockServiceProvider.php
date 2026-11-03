<?php

declare(strict_types=1);

namespace App\Domain\Stock;

use App\Domain\Stock\Models\StockLevel;
use App\Domain\Stock\Policies\StockLevelPolicy;
use App\Domain\Stock\Presentation\Livewire\Form;
use App\Domain\Stock\Presentation\Livewire\Index;
use App\Domain\Stock\Presentation\Livewire\ProductStock;
use App\Domain\Stock\Presentation\Livewire\Show;
use App\Domain\Stock\Stocktakes\Presentation\Livewire\Form as StocktakeForm;
use App\Domain\Stock\Stocktakes\Presentation\Livewire\Index as StocktakesIndex;
use App\Domain\Stock\Stocktakes\Presentation\Livewire\Show as StocktakeShow;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class StockServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadMigrationsFrom(__DIR__.'/Stocktakes/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/Presentation/Views', 'stock');
        $this->loadViewsFrom(__DIR__.'/Stocktakes/Presentation/Views', 'stocktakes');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/api.php');
        $this->loadRoutesFrom(__DIR__.'/Stocktakes/Presentation/Routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/Stocktakes/Presentation/Routes/api.php');
        Gate::policy(StockLevel::class, StockLevelPolicy::class);
        Livewire::addComponent(name: 'stock-levels.index', class: Index::class);
        Livewire::addComponent(name: 'stock-levels.form', class: Form::class);
        Livewire::addComponent(name: 'stock-levels.show', class: Show::class);
        Livewire::addComponent(name: 'products.stock', class: ProductStock::class);
        Livewire::addComponent(name: 'stocktakes.index', class: StocktakesIndex::class);
        Livewire::addComponent(name: 'stocktakes.form', class: StocktakeForm::class);
        Livewire::addComponent(name: 'stocktakes.show', class: StocktakeShow::class);
    }
}
