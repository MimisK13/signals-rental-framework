<?php

declare(strict_types=1);

namespace App\Domain\Setup;

use App\Domain\Setup\Console\SignalsSetupCommand;
use App\Domain\Setup\Presentation\Livewire\Wizard;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class SetupServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/Presentation/Views', 'setup');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/web.php');

        $this->commands([SignalsSetupCommand::class]);
        Livewire::addComponent(name: 'setup.wizard', class: Wizard::class);
    }
}
