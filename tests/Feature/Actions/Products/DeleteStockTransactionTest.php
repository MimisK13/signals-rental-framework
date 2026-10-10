<?php

use App\Actions\Products\DeleteStockTransaction;
use App\Events\AuditableEvent;
use App\Models\StockLevel;
use App\Models\StockTransaction;
use App\Models\Store;
use App\Models\User;
use App\Services\Api\WebhookService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

it('reverses a buy transaction without deleting the original fact', function (): void {
    Event::fake([AuditableEvent::class]);
    $this->actingAs(User::factory()->owner()->create());
    $store = Store::factory()->create();
    $stockLevel = StockLevel::factory()->create(['store_id' => $store->id, 'quantity_held' => 10]);
    $transaction = StockTransaction::factory()->buy()->create([
        'stock_level_id' => $stockLevel->id,
        'store_id' => $store->id,
        'quantity' => '4.0',
    ]);

    (new DeleteStockTransaction)($transaction, 'Correction');

    expect((float) $stockLevel->refresh()->quantity_held)->toBe(6.0)
        ->and(StockTransaction::query()->whereKey($transaction->id)->exists())->toBeTrue()
        ->and(StockTransaction::query()->where('reversal_of_id', $transaction->id)->count())->toBe(1);
});

it('reverses a sell transaction by adding its quantity back', function (): void {
    $this->actingAs(User::factory()->owner()->create());
    $store = Store::factory()->create();
    $stockLevel = StockLevel::factory()->create(['store_id' => $store->id, 'quantity_held' => 15]);
    $transaction = StockTransaction::factory()->sell()->create([
        'stock_level_id' => $stockLevel->id,
        'store_id' => $store->id,
        'quantity' => '5.0',
    ]);

    (new DeleteStockTransaction)($transaction, 'Correction');

    expect((float) $stockLevel->refresh()->quantity_held)->toBe(20.0);
});

it('requires a reversal reason', function (): void {
    $this->actingAs(User::factory()->owner()->create());
    $stockLevel = StockLevel::factory()->create(['quantity_held' => 10]);
    $transaction = StockTransaction::factory()->buy()->create([
        'stock_level_id' => $stockLevel->id,
        'store_id' => $stockLevel->store_id,
        'quantity' => '4.0',
    ]);

    (new DeleteStockTransaction)($transaction);
})->throws(ValidationException::class);

it('rejects a second reversal', function (): void {
    $this->actingAs(User::factory()->owner()->create());
    $stockLevel = StockLevel::factory()->create(['quantity_held' => 10]);
    $transaction = StockTransaction::factory()->buy()->create([
        'stock_level_id' => $stockLevel->id,
        'store_id' => $stockLevel->store_id,
        'quantity' => '4.0',
    ]);

    (new DeleteStockTransaction)($transaction, 'First correction');
    (new DeleteStockTransaction)($transaction, 'Second correction');
})->throws(ValidationException::class);

it('requires stock adjustment permission', function (): void {
    $this->actingAs(User::factory()->create());
    $stockLevel = StockLevel::factory()->create(['quantity_held' => 10]);
    $transaction = StockTransaction::factory()->buy()->create([
        'stock_level_id' => $stockLevel->id,
        'store_id' => $stockLevel->store_id,
        'quantity' => '4.0',
    ]);

    (new DeleteStockTransaction)($transaction, 'Correction');
})->throws(AuthorizationException::class);

it('records the reversal audit and webhook', function (): void {
    Event::fake([AuditableEvent::class]);
    $this->actingAs(User::factory()->owner()->create());
    $store = Store::factory()->create();
    $stockLevel = StockLevel::factory()->create(['store_id' => $store->id, 'quantity_held' => 10]);
    $transaction = StockTransaction::factory()->buy()->create([
        'stock_level_id' => $stockLevel->id,
        'store_id' => $store->id,
        'quantity' => '4.0',
    ]);

    $this->mock(WebhookService::class)
        ->shouldReceive('dispatch')->once()
        ->with('stock_transaction.reversed', Mockery::type('array'));

    (new DeleteStockTransaction)($transaction, 'Correction');

    Event::assertDispatched(AuditableEvent::class, fn (AuditableEvent $event): bool => $event->action === 'stock_transaction.reversed');
});
