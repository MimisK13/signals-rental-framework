<?php

declare(strict_types=1);

namespace App\Domain\Assets;

use App\Domain\Assets\Console\BackfillAssetsFromStockLevels;
use App\Domain\Assets\Console\SeedFinancialDemoCommand;
use App\Domain\Assets\Presentation\Livewire\Investment;
use App\Domain\Assets\Presentation\Livewire\Show;
use App\Domain\Assets\Presentation\Livewire\Utilization;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class AssetsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/Presentation/Views', 'assets');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/web.php');
        $this->commands([
            BackfillAssetsFromStockLevels::class,
            SeedFinancialDemoCommand::class,
        ]);
        Livewire::addComponent(name: 'assets.show', class: Show::class);
        Livewire::addComponent(name: 'assets.utilization', class: Utilization::class);
        Livewire::addComponent(name: 'assets.investment', class: Investment::class);
    }
}
