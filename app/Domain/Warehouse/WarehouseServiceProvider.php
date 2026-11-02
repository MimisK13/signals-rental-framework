<?php

declare(strict_types=1);

namespace App\Domain\Warehouse;

use App\Domain\Warehouse\Containers\Models\Container;
use App\Domain\Warehouse\Containers\Policies\ContainerPolicy;
use App\Domain\Warehouse\Containers\Presentation\Livewire\CreateContainer;
use App\Domain\Warehouse\Containers\Presentation\Livewire\Index as ContainersIndex;
use App\Domain\Warehouse\Containers\Presentation\Livewire\Pack;
use App\Domain\Warehouse\Containers\Presentation\Livewire\Repack;
use App\Domain\Warehouse\Containers\Presentation\Livewire\Show;
use App\Domain\Warehouse\Presentation\Livewire\Dashboard;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class WarehouseServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Containers/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/Containers/Presentation/Views', 'containers');
        $this->loadViewsFrom(__DIR__.'/Presentation/Views', 'warehouse');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/api.php');

        Gate::policy(Container::class, ContainerPolicy::class);

        Livewire::addComponent(name: 'containers.create', class: CreateContainer::class);
        Livewire::addComponent(name: 'containers.index', class: ContainersIndex::class);
        Livewire::addComponent(name: 'containers.pack', class: Pack::class);
        Livewire::addComponent(name: 'containers.repack', class: Repack::class);
        Livewire::addComponent(name: 'containers.show', class: Show::class);
        Livewire::addComponent(name: 'warehouse.index', class: Dashboard::class);
    }
}
