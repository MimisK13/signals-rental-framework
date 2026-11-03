<?php

declare(strict_types=1);

namespace App\Domain\VirtualStock;

use App\Domain\VirtualStock\Console\DetectVirtualStockOverruns;
use App\Domain\VirtualStock\Models\VirtualStockIntake;
use App\Domain\VirtualStock\Policies\VirtualStockIntakePolicy;
use App\Domain\VirtualStock\Presentation\Columns\VirtualStockIntakeColumnRegistry;
use App\Domain\VirtualStock\Presentation\Livewire\Form;
use App\Domain\VirtualStock\Presentation\Livewire\Index;
use App\Domain\VirtualStock\Presentation\Livewire\Show;
use App\Services\ColumnRegistryResolver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class VirtualStockServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->afterResolving(ColumnRegistryResolver::class, function (ColumnRegistryResolver $resolver): void {
            $resolver->register('virtual_stock_intakes', VirtualStockIntakeColumnRegistry::class);
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/Presentation/Views', 'virtual-stock');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/api.php');

        Gate::policy(VirtualStockIntake::class, VirtualStockIntakePolicy::class);
        $this->commands([DetectVirtualStockOverruns::class]);

        foreach ([
            'virtual-stock.index' => Index::class,
            'virtual-stock.form' => Form::class,
            'virtual-stock.show' => Show::class,
        ] as $name => $class) {
            Livewire::addComponent(name: $name, class: $class);
        }
    }
}
