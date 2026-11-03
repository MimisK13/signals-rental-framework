<?php

declare(strict_types=1);

namespace App\Domain\Assets;

use App\Domain\Assets\Console\BackfillAssetsFromStockLevels;
use App\Domain\Assets\Console\SeedFinancialDemoCommand;
use App\Domain\Assets\Presentation\Livewire\Investment;
use App\Domain\Assets\Presentation\Livewire\Show;
use App\Domain\Assets\Presentation\Livewire\Utilization;
use App\Domain\Assets\Repairs\Models\Repair;
use App\Domain\Assets\Repairs\Policies\RepairPolicy;
use App\Domain\Assets\Repairs\Presentation\Livewire\Form as RepairForm;
use App\Domain\Assets\Repairs\Presentation\Livewire\Index as RepairsIndex;
use App\Domain\Assets\Repairs\Presentation\Livewire\Show as RepairShow;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class AssetsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadMigrationsFrom(__DIR__.'/Repairs/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/Presentation/Views', 'assets');
        $this->loadViewsFrom(__DIR__.'/Repairs/Presentation/Views', 'repairs');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/Repairs/Presentation/Routes/web.php');
        Gate::policy(Repair::class, RepairPolicy::class);
        $this->commands([
            BackfillAssetsFromStockLevels::class,
            SeedFinancialDemoCommand::class,
        ]);
        Livewire::addComponent(name: 'assets.show', class: Show::class);
        Livewire::addComponent(name: 'assets.utilization', class: Utilization::class);
        Livewire::addComponent(name: 'assets.investment', class: Investment::class);
        Livewire::addComponent(name: 'repairs.index', class: RepairsIndex::class);
        Livewire::addComponent(name: 'repairs.form', class: RepairForm::class);
        Livewire::addComponent(name: 'repairs.show', class: RepairShow::class);
    }
}
