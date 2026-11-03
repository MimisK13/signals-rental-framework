<?php

declare(strict_types=1);

namespace App\Domain\Lists;

use App\Domain\Lists\Models\ListName;
use App\Domain\Lists\Policies\ListNamePolicy;
use App\Domain\Lists\Presentation\Livewire\ListNameForm;
use App\Domain\Lists\Presentation\Livewire\ListNames;
use App\Domain\Lists\Presentation\Livewire\ListValueForm;
use App\Domain\Lists\Presentation\Livewire\ListValues;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class ListsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(ListName::class, ListNamePolicy::class);

        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/Presentation/Views', 'lists');
        foreach ([
            'admin.settings.list-names' => ListNames::class,
            'admin.settings.list-name-form' => ListNameForm::class,
            'admin.settings.lists' => ListValues::class,
            'admin.settings.list-value-form' => ListValueForm::class,
        ] as $name => $class) {
            Livewire::addComponent(name: $name, class: $class);
        }

        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/api.php');
    }
}
