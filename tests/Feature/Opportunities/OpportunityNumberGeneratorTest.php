<?php

use App\Models\DocumentSequence;
use App\Models\Opportunity;
use App\Models\Store;
use App\Services\Documents\DocumentSequenceDefaults;
use App\Services\Opportunities\OpportunityNumberGenerator;
use Carbon\CarbonImmutable;

describe('OpportunityNumberGenerator', function () {
    it('formats bare zero-padded numbers without prefix or year tokens', function () {
        $store = Store::factory()->create(['timezone' => 'UTC']);
        DocumentSequence::factory()->create([
            'store_id' => $store->id,
            'document_type' => OpportunityNumberGenerator::DOCUMENT_TYPE,
            'prefix' => '',
            'include_year' => false,
            'padding' => 10,
            'next_number' => 12,
            'is_entity_number' => true,
        ]);

        $at = CarbonImmutable::parse('2026-03-01 00:00:00', 'UTC');
        $generator = app(OpportunityNumberGenerator::class);

        expect($generator->next($store->id, $at))->toBe('0000000012')
            ->and($generator->next($store->id, $at))->toBe('0000000013');
    });

    it('provisions a sequence on first use with padding from the legacy number_pad setting', function () {
        settings()->set('opportunities.number_pad', 4, 'integer');
        $store = Store::factory()->create(['timezone' => 'UTC']);
        $generator = app(OpportunityNumberGenerator::class);

        expect($generator->nextOrCreate($store->id))->toBe('0001');

        $sequence = DocumentSequence::query()
            ->where('store_id', $store->id)
            ->where('document_type', OpportunityNumberGenerator::DOCUMENT_TYPE)
            ->first();

        expect($sequence)->not->toBeNull()
            ->and($sequence->prefix)->toBe('')
            ->and($sequence->include_year)->toBeFalse()
            ->and($sequence->padding)->toBe(4)
            ->and($sequence->is_entity_number)->toBeTrue();
    });

    it('seeds next_number from the global numeric max when legacy scope was global', function () {
        settings()->set('opportunities.number_scope', 'global');
        $storeA = Store::factory()->create(['timezone' => 'UTC']);
        $storeB = Store::factory()->create(['timezone' => 'UTC']);

        Opportunity::factory()->create(['store_id' => $storeA->id, 'number' => '0000000042']);
        Opportunity::factory()->create(['store_id' => $storeB->id, 'number' => '0000000043']);

        $generator = app(OpportunityNumberGenerator::class);

        expect($generator->next($storeA->id))->toBe('0000000044')
            ->and($generator->next($storeB->id))->toBe('0000000044');
    });

    it('increments sequentially under repeated allocation within a store', function () {
        $store = Store::factory()->create(['timezone' => 'UTC']);
        DocumentSequence::factory()->create([
            'store_id' => $store->id,
            'document_type' => OpportunityNumberGenerator::DOCUMENT_TYPE,
            'prefix' => '',
            'include_year' => false,
            'padding' => 4,
            'next_number' => 1,
            'is_entity_number' => true,
        ]);

        $generator = app(OpportunityNumberGenerator::class);

        expect($generator->next($store->id))->toBe('0001')
            ->and($generator->next($store->id))->toBe('0002')
            ->and($generator->next($store->id))->toBe('0003');
    });
});

it('provisions opportunity sequences with is_entity_number via DocumentSequenceDefaults', function () {
    $store = Store::factory()->create(['timezone' => 'UTC']);

    $sequence = DocumentSequenceDefaults::ensureExists($store->id, OpportunityNumberGenerator::DOCUMENT_TYPE);

    expect($sequence->prefix)->toBe('')
        ->and($sequence->include_year)->toBeFalse()
        ->and($sequence->is_entity_number)->toBeTrue();
});
