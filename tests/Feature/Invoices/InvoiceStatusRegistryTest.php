<?php

use App\Services\Invoices\InvoiceStatusRegistry;

describe('InvoiceStatusRegistry', function () {
    it('exposes configured statuses and transitions', function () {
        $registry = app(InvoiceStatusRegistry::class);

        expect($registry->defaultStatus())->toBe('draft')
            ->and($registry->has('draft'))->toBeTrue()
            ->and($registry->has('issued'))->toBeTrue()
            ->and($registry->isTerminal('paid'))->toBeTrue()
            ->and($registry->isTerminal('draft'))->toBeFalse();
    });

    it('allows configured transitions and rejects invalid ones', function () {
        $registry = app(InvoiceStatusRegistry::class);

        expect($registry->canTransitionTo('draft', 'issued'))->toBeTrue()
            ->and($registry->canTransitionTo('draft', 'paid'))->toBeFalse()
            ->and($registry->canTransitionTo('paid', 'void'))->toBeFalse();
    });

    it('asserts transition validity', function () {
        $registry = app(InvoiceStatusRegistry::class);

        $registry->assertCanTransitionTo('draft', 'issued');

        $registry->assertCanTransitionTo('draft', 'draft');
    })->throwsNoExceptions();

    it('throws when transitioning to an unknown status', function () {
        app(InvoiceStatusRegistry::class)->assertCanTransitionTo('draft', 'mystery');
    })->throws(InvalidArgumentException::class);

    it('throws when asserting an unknown status key', function () {
        app(InvoiceStatusRegistry::class)->assertKnown('mystery');
    })->throws(InvalidArgumentException::class);
});
