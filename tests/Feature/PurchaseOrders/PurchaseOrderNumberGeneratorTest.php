<?php

use App\Models\DocumentSequence;
use App\Models\Store;
use App\Services\PurchaseOrders\PurchaseOrderNumberGenerator;
use Carbon\CarbonImmutable;

describe('PurchaseOrderNumberGenerator', function () {
    it('formats numbers from document sequences with prefix and padding', function () {
        $store = Store::factory()->create(['timezone' => 'UTC']);
        DocumentSequence::factory()->create([
            'store_id' => $store->id,
            'document_type' => PurchaseOrderNumberGenerator::DOCUMENT_TYPE,
            'prefix' => 'PO-',
            'include_year' => true,
            'padding' => 4,
            'next_number' => 7,
        ]);

        $at = CarbonImmutable::parse('2026-03-01 00:00:00', 'UTC');
        $generator = app(PurchaseOrderNumberGenerator::class);

        expect($generator->next($store->id, $at))->toBe('PO-2026-0007')
            ->and($generator->next($store->id, $at))->toBe('PO-2026-0008');
    });

    it('creates a default sequence on first use using settings prefix', function () {
        settings()->set('purchase_orders.fallback_number_prefix', 'BUY-');
        $store = Store::factory()->create(['timezone' => 'UTC']);
        $generator = app(PurchaseOrderNumberGenerator::class);

        expect($generator->nextOrCreate($store->id))
            ->toBe('BUY-'.now()->format('Y').'-0001');

        expect(DocumentSequence::query()
            ->where('store_id', $store->id)
            ->where('document_type', PurchaseOrderNumberGenerator::DOCUMENT_TYPE)
            ->exists())->toBeTrue();
    });

    it('increments sequentially under repeated allocation', function () {
        $store = Store::factory()->create(['timezone' => 'UTC']);
        DocumentSequence::factory()->create([
            'store_id' => $store->id,
            'document_type' => PurchaseOrderNumberGenerator::DOCUMENT_TYPE,
            'prefix' => 'PO-',
            'include_year' => false,
            'padding' => 3,
            'next_number' => 1,
        ]);

        $generator = app(PurchaseOrderNumberGenerator::class);

        expect($generator->next($store->id))->toBe('PO-001')
            ->and($generator->next($store->id))->toBe('PO-002')
            ->and($generator->next($store->id))->toBe('PO-003');
    });
});
