<?php

use App\Services\PurchaseOrders\PurchaseOrderStatusRegistry;

describe('PurchaseOrderStatusRegistry', function () {
    it('exposes configured statuses and transitions', function () {
        $registry = app(PurchaseOrderStatusRegistry::class);

        expect($registry->defaultStatus())->toBe('draft')
            ->and($registry->has('draft'))->toBeTrue()
            ->and($registry->has('sent'))->toBeTrue()
            ->and($registry->isTerminal('received'))->toBeTrue()
            ->and($registry->isTerminal('draft'))->toBeFalse();
    });

    it('allows configured transitions and rejects invalid ones', function () {
        $registry = app(PurchaseOrderStatusRegistry::class);

        expect($registry->canTransitionTo('draft', 'sent'))->toBeTrue()
            ->and($registry->canTransitionTo('sent', 'cancelled'))->toBeTrue()
            ->and($registry->canTransitionTo('draft', 'received'))->toBeFalse()
            ->and($registry->canTransitionTo('received', 'cancelled'))->toBeFalse()
            ->and($registry->canTransitionTo('partially_received', 'cancelled'))->toBeFalse();
    });

    it('asserts transition validity', function () {
        $registry = app(PurchaseOrderStatusRegistry::class);

        $registry->assertCanTransitionTo('draft', 'sent');
        $registry->assertCanTransitionTo('draft', 'draft');
    })->throwsNoExceptions();

    it('throws when transitioning to an unknown status', function () {
        app(PurchaseOrderStatusRegistry::class)->assertCanTransitionTo('draft', 'mystery');
    })->throws(InvalidArgumentException::class);

    it('assertKnown rejects unknown statuses', function () {
        expect(fn () => app(PurchaseOrderStatusRegistry::class)->assertKnown('mystery'))
            ->toThrow(InvalidArgumentException::class, 'Unknown purchase order status');
    });
});
