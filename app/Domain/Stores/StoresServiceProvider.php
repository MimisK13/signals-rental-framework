<?php

declare(strict_types=1);

namespace App\Domain\Stores;

use App\Domain\Stores\Models\Store;
use App\Domain\Stores\Policies\StorePolicy;
use App\Domain\Stores\Presentation\Livewire\Admin\Stores;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class StoresServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/Presentation/Views', 'stores');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/web.php');

        Gate::policy(Store::class, StorePolicy::class);
        Livewire::addComponent(name: 'admin.settings.stores', class: Stores::class);
    }
}
