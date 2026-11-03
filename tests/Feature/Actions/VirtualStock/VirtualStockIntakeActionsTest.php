<?php

use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Database\Seeders\RoleSeeder;
use App\Domain\Administration\Models\User;
use App\Domain\Products\Models\Product;
use App\Domain\VirtualStock\Actions\CancelVirtualStockIntake;
use App\Domain\VirtualStock\Actions\ConfirmVirtualStockIntake;
use App\Domain\VirtualStock\Actions\CreateVirtualStockIntake;
use App\Domain\VirtualStock\Actions\DeleteDraftVirtualStockIntake;
use App\Domain\VirtualStock\Actions\ReceiveVirtualStockIntake;
use App\Domain\VirtualStock\Actions\ReturnVirtualStockIntake;
use App\Domain\VirtualStock\Actions\UpdateDraftVirtualStockIntake;
use App\Domain\VirtualStock\Data\CreateVirtualStockIntakeData;
use App\Domain\VirtualStock\Data\UpdateDraftVirtualStockIntakeData;
use App\Domain\VirtualStock\Models\VirtualStockIntake;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('creates a draft virtual stock intake', function () {
    $intake = createTestVirtualStockIntake();

    expect($intake->status)->toBe('draft')
        ->and($intake->quantity)->toBe(5)
        ->and($intake->number)->toStartWith('VS-');
});

it('pre-fills from a sub-hire purchase order line', function () {
    $po = createTestPurchaseOrder([
        'po_type' => 'sub_hire',
        'lines' => [[
            'product_id' => Product::factory()->create()->id,
            'description' => 'Sub-hired kit',
            'quantity' => '4.00',
            'unit_cost' => 3200,
        ]],
    ]);

    $line = $po->items()->firstOrFail();

    $result = (new CreateVirtualStockIntake)(CreateVirtualStockIntakeData::from([
        'product_id' => 1,
        'store_id' => 1,
        'quantity' => 1,
        'available_from' => '2026-08-01T00:00:00Z',
        'available_until' => '2026-08-05T00:00:00Z',
        'unit_cost' => 0,
        'purchase_order_id' => $po->id,
        'purchase_order_item_id' => $line->id,
    ]));

    $intake = VirtualStockIntake::query()->findOrFail($result->id);

    expect($intake->product_id)->toBe($line->product_id)
        ->and($intake->quantity)->toBe(4)
        ->and($intake->unit_cost)->toBe(3200);
});

it('rejects virtual stock intakes from non sub-hire purchase order lines', function () {
    $po = createTestPurchaseOrder([
        'lines' => [[
            'product_id' => Product::factory()->create()->id,
            'description' => 'Widget',
            'quantity' => '1.00',
            'unit_cost' => 1000,
        ]],
    ]);

    $line = $po->items()->firstOrFail();

    expect(fn () => (new CreateVirtualStockIntake)(CreateVirtualStockIntakeData::from([
        'product_id' => $line->product_id,
        'store_id' => $po->store_id,
        'quantity' => 1,
        'available_from' => '2026-08-01T00:00:00Z',
        'available_until' => '2026-08-05T00:00:00Z',
        'unit_cost' => 100,
        'purchase_order_item_id' => $line->id,
    ])))->toThrow(ValidationException::class);
});

it('updates draft intakes only', function () {
    $intake = createTestVirtualStockIntake();

    (new UpdateDraftVirtualStockIntake)($intake, UpdateDraftVirtualStockIntakeData::from([
        'product_id' => $intake->product_id,
        'store_id' => $intake->store_id,
        'quantity' => 8,
        'available_from' => '2026-08-01T00:00:00Z',
        'available_until' => '2026-08-07T00:00:00Z',
        'unit_cost' => 2000,
    ]));

    expect($intake->refresh()->quantity)->toBe(8);
});

it('runs the confirm receive return lifecycle', function () {
    $intake = createTestVirtualStockIntake();

    app(ConfirmVirtualStockIntake::class)($intake);
    expect($intake->refresh()->status)->toBe('confirmed');

    app(ReceiveVirtualStockIntake::class)($intake);
    expect($intake->refresh()->status)->toBe('received');

    app(ReturnVirtualStockIntake::class)($intake);
    expect($intake->refresh()->status)->toBe('returned');
});

it('rejects illegal transitions', function () {
    $intake = createTestVirtualStockIntake();

    expect(fn () => app(ReceiveVirtualStockIntake::class)($intake))
        ->toThrow(AuthorizationException::class);
});

it('deletes draft intakes via event sourcing', function () {
    $intake = createTestVirtualStockIntake();

    (new DeleteDraftVirtualStockIntake)($intake);

    expect(VirtualStockIntake::query()->find($intake->id))->toBeNull()
        ->and(VirtualStockIntake::withTrashed()->find($intake->id)?->deleted_at)->not->toBeNull();
});

it('cancels confirmed intakes', function () {
    $intake = createTestVirtualStockIntake();
    app(ConfirmVirtualStockIntake::class)($intake);

    app(CancelVirtualStockIntake::class)($intake->refresh(), 'No longer needed');

    expect($intake->refresh()->status)->toBe('cancelled');
});
