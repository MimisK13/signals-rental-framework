<?php

use App\Models\Asset;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Stocktake;
use App\Models\StockTransaction;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function (): void {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->store = Store::factory()->create();
    $this->product = Product::factory()->bulk()->create(['name' => 'API Bulk Cable']);
    $this->level = StockLevel::factory()->bulk()->create([
        'store_id' => $this->store->id,
        'product_id' => $this->product->id,
        'quantity_held' => 10,
    ]);
});

function stocktakeApiToken(User $user): string
{
    return $user->createToken('stocktake-test', ['stock:read', 'stock:write'])->plainTextToken;
}

it('runs the semantic bulk stocktake API workflow without mutating inventory', function (): void {
    $token = stocktakeApiToken($this->owner);
    $headers = ['Authorization' => "Bearer {$token}"];

    $stocktake = $this->withHeaders($headers)
        ->postJson('/api/v1/stocktakes', ['store_id' => $this->store->id])
        ->assertCreated()
        ->json('stocktake');

    $this->withHeaders($headers)
        ->postJson("/api/v1/stocktakes/{$stocktake['id']}/start")
        ->assertOk()
        ->assertJsonPath('stocktake.lines.0.quantity_held_snapshot', '10.00');

    $lineId = Stocktake::findOrFail($stocktake['id'])->lines()->firstOrFail()->id;

    $this->withHeaders($headers)
        ->postJson("/api/v1/stocktakes/{$stocktake['id']}/lines/{$lineId}/count", ['counted_quantity' => 7])
        ->assertCreated()
        ->assertJsonPath('stocktake_observation.counted_quantity', '7.00');

    $this->withHeaders($headers)
        ->postJson("/api/v1/stocktakes/{$stocktake['id']}/complete")
        ->assertOk()
        ->assertJsonPath('stocktake.status', 'completed');

    expect((float) $this->level->fresh()->quantity_held)->toBe(10.0)
        ->and(StockTransaction::query()->count())->toBe(0);
});

it('requires the stock count API ability', function (): void {
    $token = $this->owner->createToken('stocktake-test', ['stock:read'])->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/stocktakes', ['store_id' => $this->store->id])
        ->assertForbidden();
});

it('records serialized scans through the semantic API', function (): void {
    $serializedProduct = Product::factory()->serialised()->create(['name' => 'API Microphone']);
    $asset = Asset::factory()->create(['product_id' => $serializedProduct->id]);
    $level = StockLevel::factory()->serialised()->create([
        'store_id' => $this->store->id,
        'product_id' => $serializedProduct->id,
        'asset_id' => $asset->id,
        'asset_number' => 'API-MIC-01',
        'serial_number' => 'API-SN-01',
        'barcode' => 'API-BC-01',
        'quantity_held' => 1,
    ]);
    $token = stocktakeApiToken($this->owner);
    $headers = ['Authorization' => "Bearer {$token}"];

    $stocktake = $this->withHeaders($headers)
        ->postJson('/api/v1/stocktakes', ['store_id' => $this->store->id])
        ->assertCreated()
        ->json('stocktake');

    $this->withHeaders($headers)
        ->postJson("/api/v1/stocktakes/{$stocktake['id']}/start")
        ->assertOk()
        ->assertJsonFragment(['asset_id' => $asset->id]);

    $this->withHeaders($headers)
        ->postJson("/api/v1/stocktakes/{$stocktake['id']}/scan", ['identifier' => 'API-BC-01'])
        ->assertCreated()
        ->assertJsonPath('stocktake_scan.expected', true)
        ->assertJsonPath('stocktake_scan.unresolved', false);

    $this->withHeaders($headers)
        ->postJson("/api/v1/stocktakes/{$stocktake['id']}/scan", ['identifier' => 'API-SN-01'])
        ->assertOk()
        ->assertJsonPath('stocktake_scan.duplicate', true);

    expect((float) $level->fresh()->quantity_held)->toBe(1.0)
        ->and(StockTransaction::query()->count())->toBe(0);
});
