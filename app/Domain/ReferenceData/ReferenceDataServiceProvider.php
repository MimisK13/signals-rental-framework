<?php

declare(strict_types=1);

namespace App\Domain\ReferenceData;

use App\Domain\ReferenceData\Presentation\Livewire\Admin\Countries;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class ReferenceDataServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/Presentation/Views', 'reference-data');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/api.php');

        Livewire::addComponent(name: 'admin.settings.countries', class: Countries::class);
    }
}
