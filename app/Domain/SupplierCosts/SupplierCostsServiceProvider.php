<?php

declare(strict_types=1);

namespace App\Domain\SupplierCosts;

use App\Domain\SupplierCosts\Models\ProductSupplierCost;
use App\Domain\SupplierCosts\Policies\ProductSupplierCostPolicy;
use App\Domain\SupplierCosts\Presentation\Livewire\ProductSupplierCosts;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class SupplierCostsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(ProductSupplierCost::class, ProductSupplierCostPolicy::class);

        $this->loadViewsFrom(__DIR__.'/Presentation/Views', 'supplier-costs');
        Livewire::addComponent(
            name: 'products.supplier-costs',
            class: ProductSupplierCosts::class,
        );

        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/api.php');
    }
}
