<?php

declare(strict_types=1);

namespace App\Domain\Products;

use App\Domain\Products\Models\Product;
use App\Domain\Products\Models\ProductGroup;
use App\Domain\Products\Policies\ProductGroupPolicy;
use App\Domain\Products\Policies\ProductPolicy;
use App\Domain\Products\Presentation\Columns\ProductColumnRegistry;
use App\Domain\Products\Presentation\Columns\ProductGroupColumnRegistry;
use App\Domain\Products\Presentation\Columns\SerialisedComponentColumnRegistry;
use App\Domain\Products\Presentation\Livewire\Accessories;
use App\Domain\Products\Presentation\Livewire\ContainerConfig;
use App\Domain\Products\Presentation\Livewire\Form;
use App\Domain\Products\Presentation\Livewire\Index;
use App\Domain\Products\Presentation\Livewire\Investment;
use App\Domain\Products\Presentation\Livewire\MergeModal;
use App\Domain\Products\Presentation\Livewire\ProductGroups\Index as ProductGroupsIndex;
use App\Domain\Products\Presentation\Livewire\Show;
use App\Services\ColumnRegistryResolver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class ProductsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->afterResolving(ColumnRegistryResolver::class, function (ColumnRegistryResolver $resolver): void {
            $resolver->register('products', ProductColumnRegistry::class);
            $resolver->register('product_groups', ProductGroupColumnRegistry::class);
            $resolver->register('serialised_components', SerialisedComponentColumnRegistry::class);
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/Presentation/Views', 'products');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/api.php');

        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(ProductGroup::class, ProductGroupPolicy::class);

        foreach ([
            'products.index' => Index::class,
            'products.form' => Form::class,
            'products.show' => Show::class,
            'products.accessories' => Accessories::class,
            'products.container-config' => ContainerConfig::class,
            'products.investment' => Investment::class,
            'products.merge-modal' => MergeModal::class,
            'product-groups.index' => ProductGroupsIndex::class,
        ] as $name => $class) {
            Livewire::addComponent(name: $name, class: $class);
        }
    }
}
