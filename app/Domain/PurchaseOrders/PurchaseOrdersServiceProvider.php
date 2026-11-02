<?php

declare(strict_types=1);

namespace App\Domain\PurchaseOrders;

use App\Domain\PurchaseOrders\Models\PurchaseOrder;
use App\Domain\PurchaseOrders\Policies\PurchaseOrderPolicy;
use App\Domain\PurchaseOrders\Presentation\Livewire\Form;
use App\Domain\PurchaseOrders\Presentation\Livewire\Index;
use App\Domain\PurchaseOrders\Presentation\Livewire\Show;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class PurchaseOrdersServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/Presentation/Views', 'purchase-orders');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/api.php');
        Gate::policy(PurchaseOrder::class, PurchaseOrderPolicy::class);
        Livewire::addComponent(name: 'purchase-orders.index', class: Index::class);
        Livewire::addComponent(name: 'purchase-orders.form', class: Form::class);
        Livewire::addComponent(name: 'purchase-orders.show', class: Show::class);
    }
}
