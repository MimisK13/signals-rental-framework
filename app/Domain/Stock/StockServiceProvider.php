<?php

declare(strict_types=1);

namespace App\Domain\Stock;

use App\Domain\Stock\Models\StockLevel;
use App\Domain\Stock\Policies\StockLevelPolicy;
use App\Domain\Stock\Presentation\Livewire\Form;
use App\Domain\Stock\Presentation\Livewire\Index;
use App\Domain\Stock\Presentation\Livewire\Show;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class StockServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/Presentation/Views', 'stock');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/api.php');
        Gate::policy(StockLevel::class, StockLevelPolicy::class);
        Livewire::addComponent(name: 'stock-levels.index', class: Index::class);
        Livewire::addComponent(name: 'stock-levels.form', class: Form::class);
        Livewire::addComponent(name: 'stock-levels.show', class: Show::class);
    }
}
