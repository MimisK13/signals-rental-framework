<?php

use App\Exceptions\Documents\DuplicateDocumentNumberException;
use App\Models\Document;
use App\Models\DocumentSequence;
use App\Models\Store;
use App\Services\Documents\DocumentNumberGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;

describe('DocumentNumberGenerator', function () {
    it('formats prefix, year, padding, and suffix', function () {
        $store = Store::factory()->create(['timezone' => 'UTC']);
        DocumentSequence::factory()->create([
            'store_id' => $store->id,
            'document_type' => 'invoice',
            'prefix' => 'INV-',
            'suffix' => '-UK',
            'include_year' => true,
            'include_month' => false,
            'padding' => 4,
            'next_number' => 42,
        ]);

        $generator = app(DocumentNumberGenerator::class);
        $at = CarbonImmutable::parse('2025-06-15 12:00:00', 'UTC');

        expect($generator->next($store->id, 'invoice', $at))->toBe('INV-2025-0042-UK')
            ->and($generator->next($store->id, 'invoice', $at))->toBe('INV-2025-0043-UK');
    });

    it('formats year and month segments when both are enabled', function () {
        $store = Store::factory()->create(['timezone' => 'UTC']);
        DocumentSequence::factory()->create([
            'store_id' => $store->id,
            'document_type' => 'quote',
            'prefix' => 'QUO-',
            'include_year' => true,
            'include_month' => true,
            'padding' => 4,
            'next_number' => 7,
        ]);

        $generator = app(DocumentNumberGenerator::class);
        $at = CarbonImmutable::parse('2025-01-10 09:00:00', 'UTC');

        expect($generator->next($store->id, 'quote', $at))->toBe('QUO-2025-01-0007');
    });

    it('formats prefix and padded number without date segments', function () {
        $store = Store::factory()->create();
        DocumentSequence::factory()->create([
            'store_id' => $store->id,
            'document_type' => 'delivery_note',
            'prefix' => 'DN',
            'include_year' => false,
            'include_month' => false,
            'padding' => 3,
            'next_number' => 5,
        ]);

        expect(app(DocumentNumberGenerator::class)->next($store->id, 'delivery_note'))
            ->toBe('DN005');
    });

    it('previews the next number without consuming the sequence', function () {
        $store = Store::factory()->create(['timezone' => 'UTC']);
        DocumentSequence::factory()->create([
            'store_id' => $store->id,
            'document_type' => 'invoice',
            'prefix' => 'INV-',
            'include_year' => true,
            'padding' => 4,
            'next_number' => 50,
        ]);

        $generator = app(DocumentNumberGenerator::class);
        $at = CarbonImmutable::parse('2026-03-01 00:00:00', 'UTC');

        expect($generator->preview($store->id, 'invoice', $at))->toBe('INV-2026-0050');

        $sequence = DocumentSequence::query()->where('store_id', $store->id)->first();
        expect($sequence?->next_number)->toBe(50);
    });

    it('resets the counter on yearly period boundaries', function () {
        $store = Store::factory()->create(['timezone' => 'UTC']);
        DocumentSequence::factory()->create([
            'store_id' => $store->id,
            'document_type' => 'invoice',
            'prefix' => 'INV-',
            'include_year' => true,
            'padding' => 4,
            'next_number' => 99,
            'reset_frequency' => 'yearly',
            'current_period_year' => 2025,
        ]);

        $generator = app(DocumentNumberGenerator::class);
        $newYear = CarbonImmutable::parse('2026-01-02 10:00:00', 'UTC');

        expect($generator->next($store->id, 'invoice', $newYear))->toBe('INV-2026-0001');

        $sequence = DocumentSequence::query()->where('store_id', $store->id)->first();
        expect($sequence?->next_number)->toBe(2)
            ->and($sequence?->current_period_year)->toBe(2026);
    });

    it('resets the counter on monthly period boundaries', function () {
        $store = Store::factory()->create(['timezone' => 'UTC']);
        DocumentSequence::factory()->create([
            'store_id' => $store->id,
            'document_type' => 'invoice',
            'prefix' => 'INV-',
            'include_year' => true,
            'include_month' => true,
            'padding' => 4,
            'next_number' => 18,
            'reset_frequency' => 'monthly',
            'current_period_year' => 2025,
            'current_period_month' => 1,
        ]);

        $generator = app(DocumentNumberGenerator::class);
        $february = CarbonImmutable::parse('2025-02-01 08:00:00', 'UTC');

        expect($generator->next($store->id, 'invoice', $february))->toBe('INV-2025-02-0001');

        $sequence = DocumentSequence::query()->where('store_id', $store->id)->first();
        expect($sequence?->next_number)->toBe(2)
            ->and($sequence?->current_period_month)->toBe(2);
    });

    it('previews a reset number without persisting the rollover', function () {
        $store = Store::factory()->create(['timezone' => 'UTC']);
        DocumentSequence::factory()->create([
            'store_id' => $store->id,
            'document_type' => 'invoice',
            'prefix' => 'INV-',
            'include_year' => true,
            'padding' => 4,
            'next_number' => 88,
            'reset_frequency' => 'yearly',
            'current_period_year' => 2025,
        ]);

        $generator = app(DocumentNumberGenerator::class);
        $newYear = CarbonImmutable::parse('2026-01-02 10:00:00', 'UTC');

        expect($generator->preview($store->id, 'invoice', $newYear))->toBe('INV-2026-0001');

        $sequence = DocumentSequence::query()->where('store_id', $store->id)->first();
        expect($sequence?->next_number)->toBe(88)
            ->and($sequence?->current_period_year)->toBe(2025);
    });

    it('validates manual numbers for uniqueness without advancing the counter', function () {
        $store = Store::factory()->create();
        DocumentSequence::factory()->create([
            'store_id' => $store->id,
            'document_type' => 'invoice',
            'prefix' => 'INV-',
            'next_number' => 10,
        ]);

        Document::factory()->create([
            'store_id' => $store->id,
            'document_type' => 'invoice',
            'document_number' => 'INV-2026-0099',
        ]);

        $generator = app(DocumentNumberGenerator::class);

        $generator->validateManualNumber($store->id, 'invoice', 'INV-2026-0100');

        expect(fn () => $generator->validateManualNumber($store->id, 'invoice', 'INV-2026-0099'))
            ->toThrow(DuplicateDocumentNumberException::class);

        expect(DocumentSequence::query()->where('store_id', $store->id)->value('next_number'))->toBe(10);
    });

    it('throws when no sequence exists for the store and type', function () {
        $store = Store::factory()->create();

        expect(fn () => app(DocumentNumberGenerator::class)->next($store->id, 'invoice'))
            ->toThrow(ModelNotFoundException::class);
    });
});
