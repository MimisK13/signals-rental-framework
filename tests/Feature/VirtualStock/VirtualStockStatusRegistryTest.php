<?php

use App\Services\VirtualStock\VirtualStockStatusRegistry;

it('exposes configured virtual stock statuses and transitions', function () {
    $registry = app(VirtualStockStatusRegistry::class);

    expect($registry->defaultStatus())->toBe('draft')
        ->and($registry->has('confirmed'))->toBeTrue()
        ->and($registry->isTerminal('returned'))->toBeTrue()
        ->and($registry->canTransitionTo('draft', 'confirmed'))->toBeTrue()
        ->and($registry->canTransitionTo('confirmed', 'received'))->toBeTrue()
        ->and($registry->canTransitionTo('received', 'returned'))->toBeTrue()
        ->and($registry->canTransitionTo('draft', 'returned'))->toBeFalse();
});

it('rejects illegal status transitions', function () {
    $registry = app(VirtualStockStatusRegistry::class);

    expect(fn () => $registry->assertCanTransitionTo('returned', 'confirmed'))
        ->toThrow(InvalidArgumentException::class);
});
