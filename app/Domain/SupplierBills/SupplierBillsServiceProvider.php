<?php

declare(strict_types=1);

namespace App\Domain\SupplierBills;

use App\Domain\SupplierBills\Models\SupplierBill;
use App\Domain\SupplierBills\Policies\SupplierBillPolicy;
use App\Domain\SupplierBills\Presentation\Livewire\Form;
use App\Domain\SupplierBills\Presentation\Livewire\Index;
use App\Domain\SupplierBills\Presentation\Livewire\Show;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class SupplierBillsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/Presentation/Views', 'supplier-bills');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/web.php');
        Gate::policy(SupplierBill::class, SupplierBillPolicy::class);
        Livewire::addComponent(name: 'supplier-bills.index', class: Index::class);
        Livewire::addComponent(name: 'supplier-bills.form', class: Form::class);
        Livewire::addComponent(name: 'supplier-bills.show', class: Show::class);
    }
}
