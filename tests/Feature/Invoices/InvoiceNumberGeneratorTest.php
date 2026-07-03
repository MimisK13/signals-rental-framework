<?php

use App\Enums\InvoiceType;
use App\Models\DocumentSequence;
use App\Models\Store;
use App\Services\Invoices\InvoiceNumberGenerator;
use Carbon\CarbonImmutable;

describe('InvoiceNumberGenerator', function () {
    it('formats numbers from per-type document sequences', function () {
        $store = Store::factory()->create(['timezone' => 'UTC']);
        DocumentSequence::factory()->create([
            'store_id' => $store->id,
            'document_type' => InvoiceType::Standard->documentSequenceType(),
            'prefix' => 'INV-',
            'include_year' => true,
            'padding' => 4,
            'next_number' => 7,
        ]);

        $at = CarbonImmutable::parse('2026-03-01 00:00:00', 'UTC');
        $generator = app(InvoiceNumberGenerator::class);

        expect($generator->next($store->id, InvoiceType::Standard, $at))->toBe('INV-2026-0007')
            ->and($generator->next($store->id, InvoiceType::Standard, $at))->toBe('INV-2026-0008');
    });

    it('keeps independent counters per invoice type', function () {
        $store = Store::factory()->create(['timezone' => 'UTC']);

        DocumentSequence::factory()->create([
            'store_id' => $store->id,
            'document_type' => InvoiceType::Standard->documentSequenceType(),
            'prefix' => 'S-',
            'include_year' => false,
            'padding' => 3,
            'next_number' => 2,
        ]);

        DocumentSequence::factory()->create([
            'store_id' => $store->id,
            'document_type' => InvoiceType::Deposit->documentSequenceType(),
            'prefix' => 'D-',
            'include_year' => false,
            'padding' => 3,
            'next_number' => 5,
        ]);

        $generator = app(InvoiceNumberGenerator::class);

        expect($generator->next($store->id, InvoiceType::Standard))->toBe('S-002')
            ->and($generator->next($store->id, InvoiceType::Deposit))->toBe('D-005');
    });

    it('creates a default sequence on first use', function () {
        $store = Store::factory()->create(['timezone' => 'UTC']);
        $generator = app(InvoiceNumberGenerator::class);

        expect($generator->nextOrCreate($store->id, InvoiceType::Proforma))
            ->toBe('INV-'.now()->format('Y').'-0001');

        expect(DocumentSequence::query()
            ->where('store_id', $store->id)
            ->where('document_type', InvoiceType::Proforma->documentSequenceType())
            ->exists())->toBeTrue();
    });
});
