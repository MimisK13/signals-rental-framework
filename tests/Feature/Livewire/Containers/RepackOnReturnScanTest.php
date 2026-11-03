<?php

use App\Domain\Availability\Contracts\AvailabilityResolutionProvider;
use App\Domain\Availability\Enums\AvailabilityResolution;
use App\Domain\Products\Enums\KitComponentBinding;
use App\Domain\Products\Models\Product;
use App\Domain\Projects\Opportunities\Actions\AddOpportunityItem;
use App\Domain\Projects\Opportunities\Actions\ConvertToOrder;
use App\Domain\Projects\Opportunities\Actions\ConvertToQuotation;
use App\Domain\Projects\Opportunities\Actions\CreateOpportunity;
use App\Domain\Projects\Opportunities\Data\AddOpportunityItemData;
use App\Domain\Projects\Opportunities\Data\CreateOpportunityData;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Stock\Models\StockLevel;
use App\Domain\Warehouse\Containers\Actions\DispatchContainer;
use App\Domain\Warehouse\Containers\Actions\PackContainerItem;
use App\Domain\Warehouse\Containers\Actions\SealContainer;
use App\Domain\Warehouse\Containers\Data\DispatchContainerData;
use App\Domain\Warehouse\Containers\Data\PackContainerItemData;
use App\Domain\Warehouse\Containers\Enums\ContainerAvailabilityMode;
use App\Domain\Warehouse\Containers\Enums\ContainerScanMode;
use App\Domain\Warehouse\Containers\Models\Container;
use App\Enums\LineItemTransactionType;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    Queue::fake();
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
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

    $this->store = Store::factory()->create(['timezone' => 'UTC']);
});

function p551RepackProduct(Store $store): Product
{
    return Product::factory()->containerable(ContainerAvailabilityMode::Transport)->create([
        'container_repack_on_return' => true,
        'container_template' => [
            'slots' => [
                [
                    'product_id' => Product::factory()->serialised()->create()->id,
                    'quantity' => 1,
                    'binding' => KitComponentBinding::Pool->value,
                ],
            ],
        ],
    ]);
}

/**
 * @return array{product: Product, housing: StockLevel, container: Container, component: StockLevel, componentProduct: Product, opportunity: Opportunity}
 */
function p551DispatchedTransportCase(Store $store, ?Product $product = null): array
{
    $product ??= p551RepackProduct($store);
    $housing = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'asset_number' => 'CASE-'.fake()->unique()->numerify('####'),
    ]);
    $container = Container::factory()->transport()->create([
        'product_id' => $product->id,
        'serialised_item_id' => $housing->id,
        'store_id' => $store->id,
        'scan_mode' => ContainerScanMode::Free->value,
    ]);

    $componentProduct = Product::factory()->serialised()->create();
    $component = StockLevel::factory()->serialised()->create([
        'product_id' => $componentProduct->id,
        'store_id' => $store->id,
    ]);

    (new PackContainerItem)($container, PackContainerItemData::from([
        'serialised_item_id' => $component->id,
    ]));
    (new SealContainer)($container);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Repack scan job',
        'store_id' => $store->id,
        'starts_at' => '2026-09-01T09:00:00Z',
        'ends_at' => '2026-09-05T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
    (new ConvertToQuotation)($opportunity);

    foreach ([$componentProduct->id => '1', $product->id => '1'] as $productId => $quantity) {
        $lineProduct = Product::query()->whereKey($productId)->firstOrFail();
        (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
            'name' => $lineProduct->name,
            'itemable_id' => $lineProduct->id,
            'itemable_type' => Product::class,
            'quantity' => $quantity,
            'transaction_type' => LineItemTransactionType::Rental->value,
        ]));
    }

    (new ConvertToOrder)($opportunity->refresh());

    (new DispatchContainer)($container, DispatchContainerData::from([
        'opportunity_id' => $opportunity->id,
    ]));

    return compact('product', 'housing', 'container', 'component', 'componentProduct', 'opportunity');
}

it('starts repack from return when scanning a dispatched housing on the containers index', function () {
    ['housing' => $housing, 'opportunity' => $opportunity] = p551DispatchedTransportCase($this->store);

    Livewire::test('containers.index')
        ->set('scanBarcode', $housing->asset_number)
        ->call('submitScan')
        ->assertHasNoErrors()
        ->assertRedirect(route('containers.repack', [
            'container' => Container::query()->active()->where('serialised_item_id', $housing->id)->firstOrFail()->uuid,
            'opportunity' => $opportunity->id,
        ]));

    expect(Container::query()->active()->where('serialised_item_id', $housing->id)->count())->toBe(1);
});

it('forbids repack scan without create permission', function () {
    ['housing' => $housing] = p551DispatchedTransportCase($this->store);

    $user = User::factory()->create();
    $user->givePermissionTo(['containers.view', 'stock.access', 'opportunities.edit']);
    $this->actingAs($user);

    Livewire::test('containers.index')
        ->set('scanBarcode', $housing->asset_number)
        ->call('submitScan')
        ->assertHasErrors('scanBarcode');
});

it('starts repack from check-in scan on the opportunity assets tab', function () {
    ['housing' => $housing, 'opportunity' => $opportunity] = p551DispatchedTransportCase($this->store);

    Livewire::test('opportunities.assets', ['opportunity' => $opportunity])
        ->set('subTab', 'check_in')
        ->set('scanAsset', $housing->asset_number)
        ->call('scanCheckIn')
        ->assertHasNoErrors()
        ->assertRedirect(route('containers.repack', [
            'container' => Container::query()->active()->where('serialised_item_id', $housing->id)->firstOrFail()->uuid,
            'opportunity' => $opportunity->id,
        ]));
});

it('falls back to creating a container when housing is not checked out', function () {
    $product = p551RepackProduct($this->store);
    $housing = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'asset_number' => 'IDLE-CASE',
    ]);

    Livewire::test('containers.index')
        ->set('scanBarcode', 'IDLE-CASE')
        ->call('submitScan')
        ->assertHasNoErrors()
        ->assertRedirect(route('containers.pack', Container::query()->active()->where('serialised_item_id', $housing->id)->firstOrFail()->uuid));
});

it('updates scan mode on open containers from the show page', function () {
    $container = Container::factory()->kit()->create([
        'store_id' => $this->store->id,
        'scan_mode' => ContainerScanMode::Strict->value,
    ]);

    Livewire::test('containers.show', ['container' => $container])
        ->set('editScanMode', ContainerScanMode::Free->value)
        ->call('saveScanMode')
        ->assertHasNoErrors();

    expect($container->fresh()->scan_mode)->toBe(ContainerScanMode::Free);
});

it('rejects scan mode updates on sealed containers from the show page', function () {
    $container = Container::factory()->kit()->sealed()->create([
        'store_id' => $this->store->id,
        'scan_mode' => ContainerScanMode::Strict->value,
    ]);

    Livewire::test('containers.show', ['container' => $container])
        ->assertSee('Strict')
        ->assertDontSeeHtml('wire:submit="saveScanMode"');
});
