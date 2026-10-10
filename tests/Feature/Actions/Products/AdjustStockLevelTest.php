<?php

use App\Actions\Products\AdjustStockLevel;
use App\Data\Products\AdjustStockLevelData;
use App\Enums\TransactionType;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockTransaction;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
    $this->store = Store::factory()->create();
    $this->product = Product::factory()->bulk()->create();
});

function adjustmentData(int $type = 2, string $quantity = '3', string $reason = 'Damaged stock'): AdjustStockLevelData
{
    return AdjustStockLevelData::from([
        'transaction_type' => $type,
        'quantity' => $quantity,
        'reason' => $reason,
    ]);
}

it('creates an increase through the D1 mutation boundary', function (): void {
    $stockLevel = StockLevel::factory()->bulk()->create([
        'product_id' => $this->product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 10,
    ]);

    $result = (new AdjustStockLevel)($stockLevel, adjustmentData(TransactionType::Increase->value, '3', 'Found stock'));
    $transaction = StockTransaction::findOrFail($result->id);

    expect((float) $stockLevel->fresh()->quantity_held)->toBe(13.0)
        ->and((float) $transaction->quantity_before)->toBe(10.0)
        ->and((float) $transaction->quantity_after)->toBe(13.0)
        ->and($transaction->quantity_move)->toBe('3.0')
        ->and($transaction->actor_user_id)->toBe($this->user->id)
        ->and($transaction->description)->toBe('Found stock')
        ->and($transaction->manual)->toBeTrue()
        ->and($transaction->source_id)->toBeNull();
});

it('creates a decrease and write-off for bulk stock', function (int $type): void {
    $stockLevel = StockLevel::factory()->bulk()->create([
        'product_id' => $this->product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 10,
    ]);

    $result = (new AdjustStockLevel)($stockLevel, adjustmentData($type, '3', 'Known stock correction'));
    $transaction = StockTransaction::findOrFail($result->id);

    expect((float) $stockLevel->fresh()->quantity_held)->toBe(7.0)
        ->and($transaction->quantity_move)->toBe('-3.0')
        ->and($transaction->manual)->toBeTrue();
})->with([
    'decrease' => TransactionType::Decrease->value,
    'write off' => TransactionType::WriteOff->value,
]);

it('rejects protected transaction types', function (int $type): void {
    $stockLevel = StockLevel::factory()->bulk()->create([
        'product_id' => $this->product->id,
        'store_id' => $this->store->id,
    ]);

    expect(fn () => (new AdjustStockLevel)($stockLevel, adjustmentData($type)))
        ->toThrow(ValidationException::class);
})->with([
    'opening' => TransactionType::Opening->value,
    'buy' => TransactionType::Buy->value,
    'find' => TransactionType::Find->value,
    'sell' => TransactionType::Sell->value,
    'return' => TransactionType::Return->value,
    'make' => TransactionType::Make->value,
    'transfer out' => TransactionType::TransferOut->value,
    'transfer in' => TransactionType::TransferIn->value,
]);

it('rejects invalid quantity and blank reason', function (string $quantity, string $reason): void {
    $stockLevel = StockLevel::factory()->bulk()->create([
        'product_id' => $this->product->id,
        'store_id' => $this->store->id,
    ]);

    expect(fn () => (new AdjustStockLevel)($stockLevel, adjustmentData(quantity: $quantity, reason: $reason)))
        ->toThrow(ValidationException::class);
})->with([
    'zero' => ['0', 'reason'],
    'negative' => ['-1', 'reason'],
    'fraction' => ['1.5', 'reason'],
    'blank reason' => ['1', '   '],
]);

it('delegates negative-result protection to CreateStockTransaction', function (): void {
    $stockLevel = StockLevel::factory()->bulk()->create([
        'product_id' => $this->product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 2,
    ]);

    expect(fn () => (new AdjustStockLevel)($stockLevel, adjustmentData(TransactionType::Decrease->value, '3')))
        ->toThrow(ValidationException::class);

    expect((float) $stockLevel->fresh()->quantity_held)->toBe(2.0)
        ->and(StockTransaction::query()->where('stock_level_id', $stockLevel->id)->count())->toBe(0);
});

it('rejects serialized, virtual, and container-associated stock', function (string $kind): void {
    $stockLevel = match ($kind) {
        'serialized' => StockLevel::factory()->serialised()->create([
            'product_id' => Product::factory()->serialised()->create()->id,
            'store_id' => $this->store->id,
        ]),
        'virtual' => StockLevel::factory()->bulk()->create([
            'product_id' => $this->product->id,
            'store_id' => $this->store->id,
            'is_virtual' => true,
        ]),
        'container' => StockLevel::factory()->bulk()->create([
            'product_id' => $this->product->id,
            'store_id' => $this->store->id,
            'container_stock_level_id' => StockLevel::factory()->bulk()->create([
                'product_id' => $this->product->id,
                'store_id' => $this->store->id,
            ])->id,
        ]),
        default => throw new InvalidArgumentException("Unsupported stock kind: {$kind}"),
    };

    expect(fn () => (new AdjustStockLevel)($stockLevel, adjustmentData()))
        ->toThrow(ValidationException::class);
})->with(['serialized', 'virtual', 'container']);

it('preserves D1 authorization for the concrete Store', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo('stock.adjust');
    $this->actingAs($user);
    $stockLevel = StockLevel::factory()->bulk()->create([
        'product_id' => $this->product->id,
        'store_id' => $this->store->id,
    ]);

    expect(fn () => (new AdjustStockLevel)($stockLevel, adjustmentData()))
        ->toThrow(AuthorizationException::class);
});
