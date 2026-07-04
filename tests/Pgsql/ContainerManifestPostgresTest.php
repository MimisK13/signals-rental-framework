<?php

use App\Actions\Containers\PackContainerItem;
use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Data\Containers\PackContainerItemData;
use App\Enums\AvailabilityResolution;
use App\Models\Container;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Services\Containers\ContainerManifestService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Tests\Concerns\UsesPostgres;

/*
|--------------------------------------------------------------------------
| PostgreSQL container-manifest recursive CTE lane
|--------------------------------------------------------------------------
|
| Proves depth-limited recursive manifest roll-up via the PostgreSQL CTE path
| in {@see ContainerManifestService}.
|
|   php artisan test --compact --group=pgsql
*/

uses(UsesPostgres::class)->group('pgsql');

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());

    $this->app->bind(AvailabilityResolutionProvider::class, fn () => new class implements AvailabilityResolutionProvider
    {
        public function resolve(): AvailabilityResolution
        {
            return AvailabilityResolution::Daily;
        }
    });

    $this->store = Store::factory()->create();
});

/**
 * Kit container with an explicit serialised housing (the container factory's
 * default housing belongs to a bulk product, which the pack guard rejects).
 */
function pgKitContainer(Store $store): Container
{
    $product = Product::factory()->containerable()->create();
    $housing = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
    ]);

    return Container::factory()->create([
        'product_id' => $product->id,
        'serialised_item_id' => $housing->id,
        'store_id' => $store->id,
    ]);
}

it('rolls up nested contents via recursive CTE at max depth', function () {
    $component = Product::factory()->serialised()->create();

    $inner = pgKitContainer($this->store);
    $innerAsset = StockLevel::factory()->serialised()->create([
        'product_id' => $component->id,
        'store_id' => $this->store->id,
    ]);
    (new PackContainerItem)($inner, PackContainerItemData::from([
        'serialised_item_id' => $innerAsset->id,
    ]));

    $outerProduct = Product::factory()->containerable()->create([
        'container_max_nesting_depth' => 3,
    ]);
    $outerHousing = StockLevel::factory()->serialised()->create([
        'product_id' => $outerProduct->id,
        'store_id' => $this->store->id,
    ]);
    $outer = Container::factory()->create([
        'product_id' => $outerProduct->id,
        'serialised_item_id' => $outerHousing->id,
        'store_id' => $this->store->id,
    ]);

    (new PackContainerItem)($outer, PackContainerItemData::from([
        'serialised_item_id' => $inner->serialised_item_id,
    ]));

    $manifest = app(ContainerManifestService::class)->manifestQuantities($outer);

    expect($manifest[$component->id] ?? 0)->toBe(1);
});

it('excludes contents beyond the configured max nesting depth in the CTE path', function () {
    $component = Product::factory()->serialised()->create();

    $depth3 = pgKitContainer($this->store);
    $depth3Asset = StockLevel::factory()->serialised()->create([
        'product_id' => $component->id,
        'store_id' => $this->store->id,
    ]);
    (new PackContainerItem)($depth3, PackContainerItemData::from([
        'serialised_item_id' => $depth3Asset->id,
    ]));

    $depth2 = pgKitContainer($this->store);
    (new PackContainerItem)($depth2, PackContainerItemData::from([
        'serialised_item_id' => $depth3->serialised_item_id,
    ]));

    $rootProduct = Product::factory()->containerable()->create([
        'container_max_nesting_depth' => 2,
    ]);
    $root = Container::factory()->create([
        'product_id' => $rootProduct->id,
        'serialised_item_id' => StockLevel::factory()->serialised()->create([
            'product_id' => $rootProduct->id,
            'store_id' => $this->store->id,
        ])->id,
        'store_id' => $this->store->id,
    ]);

    (new PackContainerItem)($root, PackContainerItemData::from([
        'serialised_item_id' => $depth2->serialised_item_id,
    ]));

    $manifest = app(ContainerManifestService::class)->manifestQuantities($root);

    expect($manifest)->not->toHaveKey($component->id);
});
