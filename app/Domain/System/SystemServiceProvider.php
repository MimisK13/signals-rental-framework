<?php

declare(strict_types=1);

namespace App\Domain\System;

use App\Domain\System\Console\SignalsStatusCommand;
use App\Domain\System\Presentation\Livewire\Admin\Infrastructure;
use App\Domain\System\Presentation\Livewire\Admin\Seeders;
use App\Domain\System\Presentation\Livewire\Admin\SystemHealth;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class SystemServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/Presentation/Views', 'system');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/web.php');

        $this->commands([SignalsStatusCommand::class]);

        Livewire::addComponent(name: 'admin.settings.system-health', class: SystemHealth::class);
        Livewire::addComponent(name: 'admin.settings.infrastructure', class: Infrastructure::class);
        Livewire::addComponent(name: 'admin.settings.seeders', class: Seeders::class);
    }
}
