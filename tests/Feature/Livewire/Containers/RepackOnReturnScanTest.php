<?php

use App\Actions\Containers\DispatchContainer;
use App\Actions\Containers\PackContainerItem;
use App\Actions\Containers\SealContainer;
use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Data\Containers\DispatchContainerData;
use App\Data\Containers\PackContainerItemData;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\AvailabilityResolution;
use App\Enums\ContainerAvailabilityMode;
use App\Enums\ContainerScanMode;
use App\Enums\KitComponentBinding;
use App\Enums\LineItemTransactionType;
use App\Models\Container;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Queue;
use Livewire\Volt\Volt;

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

    Volt::test('containers.index')
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

    Volt::test('containers.index')
        ->set('scanBarcode', $housing->asset_number)
        ->call('submitScan')
        ->assertHasErrors('scanBarcode');
});

it('starts repack from check-in scan on the opportunity assets tab', function () {
    ['housing' => $housing, 'opportunity' => $opportunity] = p551DispatchedTransportCase($this->store);

    Volt::test('opportunities.assets', ['opportunity' => $opportunity])
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

    Volt::test('containers.index')
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

    Volt::test('containers.show', ['container' => $container])
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

    Volt::test('containers.show', ['container' => $container])
        ->assertSee('Strict')
        ->assertDontSeeHtml('wire:submit="saveScanMode"');
});
