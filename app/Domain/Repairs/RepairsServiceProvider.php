<?php

declare(strict_types=1);

namespace App\Domain\Repairs;

use App\Domain\Repairs\Models\Repair;
use App\Domain\Repairs\Policies\RepairPolicy;
use App\Domain\Repairs\Presentation\Livewire\Form;
use App\Domain\Repairs\Presentation\Livewire\Index;
use App\Domain\Repairs\Presentation\Livewire\Show;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class RepairsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/Presentation/Views', 'repairs');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/web.php');
        Gate::policy(Repair::class, RepairPolicy::class);
        Livewire::addComponent(name: 'repairs.index', class: Index::class);
        Livewire::addComponent(name: 'repairs.form', class: Form::class);
        Livewire::addComponent(name: 'repairs.show', class: Show::class);
    }
}
