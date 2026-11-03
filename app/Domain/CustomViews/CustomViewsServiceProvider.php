<?php

declare(strict_types=1);

namespace App\Domain\CustomViews;

use App\Domain\CustomViews\Models\CustomView;
use App\Domain\CustomViews\Policies\CustomViewPolicy;
use App\Domain\CustomViews\Presentation\Livewire\ViewBuilder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class CustomViewsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/Presentation/Views', 'custom-views');
        Gate::policy(CustomView::class, CustomViewPolicy::class);

        Livewire::addComponent(name: 'components.view-builder', class: ViewBuilder::class);

        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/api.php');
    }
}
