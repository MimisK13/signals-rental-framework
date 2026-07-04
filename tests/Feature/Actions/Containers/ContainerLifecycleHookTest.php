<?php

use App\Actions\Containers\PackContainerItem;
use App\Data\Containers\PackContainerItemData;
use App\Events\Containers\BeforeContainerPacked;
use App\Models\Container;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    $this->store = Store::factory()->create();
});

it('allows before_pack listeners to veto packing', function () {
    Event::listen(BeforeContainerPacked::class, function (): void {
        throw ValidationException::withMessages(['pack' => 'Blocked by plugin.']);
    });

    $container = Container::factory()->kit()->create(['store_id' => $this->store->id]);
    $asset = StockLevel::factory()->serialised()->create([
        'product_id' => Product::factory()->serialised()->create()->id,
        'store_id' => $this->store->id,
    ]);

    expect(fn () => (new PackContainerItem)($container, PackContainerItemData::from([
        'serialised_item_id' => $asset->id,
    ])))->toThrow(ValidationException::class, 'Blocked by plugin.');
});
