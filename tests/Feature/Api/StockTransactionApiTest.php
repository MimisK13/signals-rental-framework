<?php

use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockTransaction;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->product = Product::factory()->create();
    $this->store = Store::factory()->create();
    $this->stockLevel = StockLevel::factory()->create([
        'product_id' => $this->product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 0,
    ]);
});

describe('GET stock_transactions', function () {
    it('lists transactions for a stock level', function () {
        StockTransaction::factory()->count(3)->create([
            'stock_level_id' => $this->stockLevel->id,
            'store_id' => $this->store->id,
        ]);
        $token = $this->owner->createToken('test', ['stock:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/products/{$this->product->id}/stock_levels/{$this->stockLevel->id}/stock_transactions")
            ->assertOk()
            ->assertJsonStructure([
                'stock_transactions' => [
                    '*' => ['id', 'stock_level_id', 'store_id', 'transaction_type', 'transaction_type_name', 'quantity', 'quantity_move', 'manual'],
                ],
                'meta' => ['total', 'per_page', 'page'],
            ])
            ->assertJsonPath('meta.total', 3);
    });

    it('requires authentication', function () {
        $this->getJson("/api/v1/products/{$this->product->id}/stock_levels/{$this->stockLevel->id}/stock_transactions")
            ->assertUnauthorized();
    });

    it('returns forbidden without proper ability', function () {
        $token = $this->owner->createToken('test', ['products:read'])->plainTextToken;
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/products/{$this->product->id}/stock_levels/{$this->stockLevel->id}/stock_transactions")
            ->assertForbidden();
    });
});

describe('GET stock_transactions (Ransack filtering)', function () {
    it('filters by transaction_type with the _eq predicate', function () {
        StockTransaction::factory()->buy()->count(2)->create([
            'stock_level_id' => $this->stockLevel->id,
            'store_id' => $this->store->id,
        ]);
        StockTransaction::factory()->sell()->create([
            'stock_level_id' => $this->stockLevel->id,
            'store_id' => $this->store->id,
        ]);
        $token = $this->owner->createToken('test', ['stock:read'])->plainTextToken;

        // Buy = 4
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/products/{$this->product->id}/stock_levels/{$this->stockLevel->id}/stock_transactions?q[transaction_type_eq]=4")
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonCount(2, 'stock_transactions');
    });

    it('honours a q[s] sort instead of the transaction_at fallback', function () {
        $early = StockTransaction::factory()->buy()->create([
            'stock_level_id' => $this->stockLevel->id,
            'store_id' => $this->store->id,
            'transaction_at' => now()->subDays(2),
        ]);
        $late = StockTransaction::factory()->buy()->create([
            'stock_level_id' => $this->stockLevel->id,
            'store_id' => $this->store->id,
            'transaction_at' => now(),
        ]);
        $token = $this->owner->createToken('test', ['stock:read'])->plainTextToken;

        // Default ordering is transaction_at DESC; an explicit ASC sort must win
        // (the hasExplicitSort guard suppresses the fallback orderBy).
        $ids = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/products/{$this->product->id}/stock_levels/{$this->stockLevel->id}/stock_transactions?q[s]=transaction_at%20asc")
            ->assertOk()
            ->json('stock_transactions.*.id');

        expect($ids)->toBe([$early->id, $late->id]);
    });

    it('filters by the manual flag with the _eq predicate', function () {
        // Buy is manual; Opening (system type) is not manual.
        StockTransaction::factory()->buy()->create([
            'stock_level_id' => $this->stockLevel->id,
            'store_id' => $this->store->id,
        ]);
        StockTransaction::factory()->opening()->create([
            'stock_level_id' => $this->stockLevel->id,
            'store_id' => $this->store->id,
        ]);
        $token = $this->owner->createToken('test', ['stock:read'])->plainTextToken;

        // The integer boolean form (1/0) is the SQLite-safe `eq` predicate.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/products/{$this->product->id}/stock_levels/{$this->stockLevel->id}/stock_transactions?q[manual_eq]=1")
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    });
});

describe('DELETE stock_transactions/{id}', function () {
    it('reverses a transaction and preserves the ledger row', function () {
        // Seed held stock then add a buy transaction worth +4.
        $this->stockLevel->update(['quantity_held' => 10]);
        $txn = StockTransaction::factory()->buy()->create([
            'stock_level_id' => $this->stockLevel->id,
            'store_id' => $this->store->id,
            'quantity' => '4.0',
        ]);
        $token = $this->owner->createToken('test', ['stock:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/products/{$this->product->id}/stock_levels/{$this->stockLevel->id}/stock_transactions/{$txn->id}", ['reason' => 'Correction'])
            ->assertNoContent();

        $this->assertDatabaseHas('stock_transactions', ['id' => $txn->id]);
        $this->assertDatabaseHas('stock_transactions', ['reversal_of_id' => $txn->id]);

        // Deleting a +4 buy decrements held stock back to 6.
        $this->stockLevel->refresh();
        expect((float) $this->stockLevel->quantity_held)->toBe(6.0);
    });

    it('returns forbidden without the stock:write ability', function () {
        $txn = StockTransaction::factory()->buy()->create([
            'stock_level_id' => $this->stockLevel->id,
            'store_id' => $this->store->id,
        ]);
        // stock:read alone is insufficient for a delete (needs stock:write).
        $token = $this->owner->createToken('test', ['stock:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/products/{$this->product->id}/stock_levels/{$this->stockLevel->id}/stock_transactions/{$txn->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('stock_transactions', ['id' => $txn->id]);
    });

    it('requires authentication', function () {
        $txn = StockTransaction::factory()->buy()->create([
            'stock_level_id' => $this->stockLevel->id,
            'store_id' => $this->store->id,
        ]);

        $this->deleteJson("/api/v1/products/{$this->product->id}/stock_levels/{$this->stockLevel->id}/stock_transactions/{$txn->id}")
            ->assertUnauthorized();
    });

    it('returns 404 when the transaction belongs to a different stock level', function () {
        $otherStockLevel = StockLevel::factory()->create();
        $txn = StockTransaction::factory()->buy()->create([
            'stock_level_id' => $otherStockLevel->id,
            'store_id' => $this->store->id,
        ]);
        $token = $this->owner->createToken('test', ['stock:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/products/{$this->product->id}/stock_levels/{$this->stockLevel->id}/stock_transactions/{$txn->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('stock_transactions', ['id' => $txn->id]);
    });
});

describe('GET stock_transactions/{id}', function () {
    it('shows a single transaction', function () {
        $txn = StockTransaction::factory()->create([
            'stock_level_id' => $this->stockLevel->id,
            'store_id' => $this->store->id,
        ]);
        $token = $this->owner->createToken('test', ['stock:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/products/{$this->product->id}/stock_levels/{$this->stockLevel->id}/stock_transactions/{$txn->id}")
            ->assertOk()
            ->assertJsonPath('stock_transaction.id', $txn->id);
    });

    it('returns 404 for transaction on wrong stock level', function () {
        $otherStockLevel = StockLevel::factory()->create();
        $txn = StockTransaction::factory()->create([
            'stock_level_id' => $otherStockLevel->id,
            'store_id' => $this->store->id,
        ]);
        $token = $this->owner->createToken('test', ['stock:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/products/{$this->product->id}/stock_levels/{$this->stockLevel->id}/stock_transactions/{$txn->id}")
            ->assertNotFound();
    });
});

describe('POST stock transaction adjustments', function () {
    it('creates an increase through the compatibility route', function () {
        $token = $this->owner->createToken('test', ['stock:write'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/products/{$this->product->id}/stock_levels/{$this->stockLevel->id}/stock_transactions", [
                'transaction_type' => 2,
                'quantity' => '5',
                'reason' => 'Found stock',
            ])
            ->assertCreated();

        expect($response->json('stock_transaction.transaction_type'))->toBe(2);
        expect($response->json('stock_transaction.transaction_type_name'))->toBe('Increase');
        expect($response->json('stock_transaction.quantity'))->toBe('5.0');
        expect($response->json('stock_transaction.quantity_move'))->toBe('5.0');
        expect($response->json('stock_transaction.manual'))->toBeTrue();
    });

    it('updates stock level quantity on an adjustment', function () {
        $token = $this->owner->createToken('test', ['stock:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/products/{$this->product->id}/stock_levels/{$this->stockLevel->id}/stock_transactions", [
                'transaction_type' => 2,
                'quantity' => '10',
                'reason' => 'Stock correction',
            ])
            ->assertCreated();

        $this->stockLevel->refresh();
        expect((float) $this->stockLevel->quantity_held)->toBe(10.0);
    });

    it('rejects invalid transaction types', function () {
        $token = $this->owner->createToken('test', ['stock:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/products/{$this->product->id}/stock_levels/{$this->stockLevel->id}/stock_transactions", [
                'transaction_type' => 999, // not a valid TransactionType
                'quantity' => '1.0',
                'transaction_at' => now()->toISOString(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('transaction_type');
    });

    it('rejects system transaction types via the public adjustment API', function () {
        $token = $this->owner->createToken('test', ['stock:write'])->plainTextToken;

        // Opening (1) is a system type — API-postable (not offered in the UI form).
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/products/{$this->product->id}/stock_levels/{$this->stockLevel->id}/stock_transactions", [
                'transaction_type' => 1,
                'quantity' => '5.0',
                'reason' => 'Should not be allowed',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('transaction_type');
    });

    it('requires authentication', function () {
        $this->postJson("/api/v1/products/{$this->product->id}/stock_levels/{$this->stockLevel->id}/stock_transactions", [
            'transaction_type' => 2,
            'quantity' => '1.0',
            'reason' => 'Stock correction',
        ])->assertUnauthorized();
    });

    it('returns 404 when stock level does not belong to product', function () {
        $otherProduct = Product::factory()->create();
        $token = $this->owner->createToken('test', ['stock:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/products/{$otherProduct->id}/stock_levels/{$this->stockLevel->id}/stock_transactions", [
                'transaction_type' => 2,
                'quantity' => '1.0',
                'reason' => 'Stock correction',
            ])
            ->assertNotFound();
    });

    it('matches RMS response shape', function () {
        $token = $this->owner->createToken('test', ['stock:write'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/products/{$this->product->id}/stock_levels/{$this->stockLevel->id}/stock_transactions", [
                'transaction_type' => 2,
                'quantity' => '2',
                'reason' => 'Found in warehouse',
            ])
            ->assertCreated();

        $data = $response->json('stock_transaction');
        expect($data)->toHaveKeys([
            'id', 'stock_level_id', 'store_id', 'source_id', 'source_type',
            'transaction_type', 'transaction_type_name', 'transaction_at',
            'quantity', 'quantity_move', 'description', 'manual',
            'created_at', 'updated_at',
        ]);
    });

    it('supports the explicit adjustment endpoint', function () {
        $token = $this->owner->createToken('test', ['stock:write'])->plainTextToken;
        $this->stockLevel->update(['quantity_held' => 3]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/products/{$this->product->id}/stock_levels/{$this->stockLevel->id}/adjust", [
                'type' => 'write_off',
                'quantity' => 2,
                'reason' => 'Damaged beyond repair',
            ])
            ->assertCreated();

        expect($response->json('stock_transaction.transaction_type_name'))->toBe('Write Off');
    });

    it('rejects caller-controlled provenance fields', function () {
        $token = $this->owner->createToken('test', ['stock:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/products/{$this->product->id}/stock_levels/{$this->stockLevel->id}/adjust", [
                'type' => 'increase',
                'quantity' => 1,
                'reason' => 'Stock correction',
                'store_id' => Store::factory()->create()->id,
                'transaction_at' => '2000-01-01T00:00:00Z',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['store_id', 'transaction_at']);
    });
});
