<?php

use App\Actions\Products\CreateStockTransaction;
use App\Data\Products\CreateStockTransactionData;
use App\Enums\TransactionType;
use App\Events\AuditableEvent;
use App\Models\Member;
use App\Models\StockLevel;
use App\Models\StockTransaction;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

it('creates a stock transaction and updates quantity_held', function () {
    Event::fake([AuditableEvent::class]);

    $user = User::factory()->owner()->create();
    $this->actingAs($user);
    $store = Store::factory()->create();
    $stockLevel = StockLevel::factory()->create(['store_id' => $store->id, 'quantity_held' => 0]);

    $dto = CreateStockTransactionData::from([
        'stock_level_id' => $stockLevel->id,
        'store_id' => $store->id,
        'transaction_type' => TransactionType::Buy->value,
        'quantity' => '10.0',
        'transaction_at' => now()->toISOString(),
    ]);

    $result = (new CreateStockTransaction)($dto);

    expect($result->transaction_type)->toBe(TransactionType::Buy->value);
    $stockLevel->refresh();
    expect((float) $stockLevel->quantity_held)->toBe(10.0);
    $transaction = StockTransaction::query()->latest('id')->firstOrFail();
    expect($transaction->actor_user_id)->toBe($user->id)
        ->and((float) $transaction->quantity_before)->toBe(0.0)
        ->and((float) $transaction->quantity_after)->toBe(10.0)
        ->and($transaction->store_id)->toBe($store->id);

    Event::assertDispatched(AuditableEvent::class, function (AuditableEvent $event) {
        return $event->action === 'stock_transaction.created';
    });
});

it('rejects a conflicting caller store and negative resulting quantity', function (): void {
    $user = User::factory()->owner()->create();
    $this->actingAs($user);
    $stockLevel = StockLevel::factory()->create(['quantity_held' => 5]);
    $foreignStore = Store::factory()->create();

    (new CreateStockTransaction)(CreateStockTransactionData::from([
        'stock_level_id' => $stockLevel->id,
        'store_id' => $foreignStore->id,
        'transaction_type' => TransactionType::Sell->value,
        'quantity' => '1.0',
    ]));
})->throws(ValidationException::class);

it('rejects a transaction that would make quantity negative', function (): void {
    $this->actingAs(User::factory()->owner()->create());
    $stockLevel = StockLevel::factory()->create(['quantity_held' => 5]);

    (new CreateStockTransaction)(CreateStockTransactionData::from([
        'stock_level_id' => $stockLevel->id,
        'transaction_type' => TransactionType::Sell->value,
        'quantity' => '6.0',
    ]));
})->throws(ValidationException::class);

it('does not allow committed ledger facts to be edited or deleted', function (): void {
    $transaction = StockTransaction::factory()->buy()->create();

    expect(fn (): bool => $transaction->update(['description' => 'changed']))
        ->toThrow(LogicException::class)
        ->and(fn (): bool => $transaction->delete())
        ->toThrow(LogicException::class);
});

it('decrements quantity for sell transactions', function () {
    Event::fake([AuditableEvent::class]);

    $user = User::factory()->owner()->create();
    $this->actingAs($user);
    $store = Store::factory()->create();
    $stockLevel = StockLevel::factory()->create(['store_id' => $store->id, 'quantity_held' => 20]);

    $dto = CreateStockTransactionData::from([
        'stock_level_id' => $stockLevel->id,
        'store_id' => $store->id,
        'transaction_type' => TransactionType::Sell->value,
        'quantity' => '5.0',
        'transaction_at' => now()->toISOString(),
    ]);

    (new CreateStockTransaction)($dto);

    $stockLevel->refresh();
    expect((float) $stockLevel->quantity_held)->toBe(15.0);

    Event::assertDispatched(AuditableEvent::class);
});

it('records an action_logs row when a stock transaction is created', function () {
    $user = User::factory()->owner()->create();
    $this->actingAs($user);
    $store = Store::factory()->create();
    $stockLevel = StockLevel::factory()->create(['store_id' => $store->id, 'quantity_held' => 0]);

    $result = (new CreateStockTransaction)(CreateStockTransactionData::from([
        'stock_level_id' => $stockLevel->id,
        'store_id' => $store->id,
        'transaction_type' => TransactionType::Buy->value,
        'quantity' => '10.0',
        'transaction_at' => now()->toISOString(),
    ]));

    assertActionLogged('stock_transaction.created', StockTransaction::class, $result->id, $user->id);
});

it('throws authorization exception without permission', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $stockLevel = StockLevel::factory()->create();

    $dto = CreateStockTransactionData::from([
        'stock_level_id' => $stockLevel->id,
        'transaction_type' => TransactionType::Buy->value,
        'quantity' => '1.0',
        'transaction_at' => now()->toISOString(),
    ]);

    (new CreateStockTransaction)($dto);
})->throws(AuthorizationException::class);

it('requires access to the concrete Stock Level store', function (): void {
    $member = Member::factory()->create();
    $user = User::factory()->create(['member_id' => $member->id]);
    $user->givePermissionTo('stock.adjust');
    $this->actingAs($user);
    $stockLevel = StockLevel::factory()->create(['quantity_held' => 0]);

    (new CreateStockTransaction)(CreateStockTransactionData::from([
        'stock_level_id' => $stockLevel->id,
        'transaction_type' => TransactionType::Buy->value,
        'quantity' => '1.0',
    ]));
})->throws(AuthorizationException::class);
