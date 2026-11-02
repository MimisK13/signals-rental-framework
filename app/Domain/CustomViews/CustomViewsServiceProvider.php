<?php

declare(strict_types=1);

namespace App\Domain\CustomViews;

use App\Domain\CustomViews\Models\CustomView;
use App\Domain\CustomViews\Policies\CustomViewPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class CustomViewsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        Gate::policy(CustomView::class, CustomViewPolicy::class);

        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/api.php');
    }
}
