<?php

use App\Actions\Documents\UpdateDocumentSequence;
use App\Data\Documents\UpdateDocumentSequenceData;
use App\Models\DocumentSequence;
use App\Models\Invoice;
use App\Models\Store;
use App\Models\User;
use App\Services\SequenceAllocator;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->create(['is_owner' => true, 'is_active' => true]));
});

describe('UpdateDocumentSequenceData padding validation', function () {
    it('rejects a next number wider than the padding digit count', function () {
        expect(fn () => UpdateDocumentSequenceData::assertNextNumberFitsPadding(10000, 4))
            ->toThrow(ValidationException::class);

        try {
            UpdateDocumentSequenceData::assertNextNumberFitsPadding(10000, 4);
        } catch (ValidationException $exception) {
            expect($exception->errors()['next_number'][0])
                ->toContain('padding digit count (4 digits)');
        }
    });

    it('accepts a next number that exactly fits the padding digit count', function () {
        UpdateDocumentSequenceData::assertNextNumberFitsPadding(9999, 4);

        expect(true)->toBeTrue();
    });

    it('rejects padding overflow via API when both fields are present', function () {
        Sanctum::actingAs(User::factory()->create(['is_owner' => true, 'is_active' => true]), ['*']);

        $store = Store::factory()->create();
        $sequence = DocumentSequence::factory()->create([
            'store_id' => $store->id,
            'document_type' => 'quote',
        ]);

        $this->putJson("/api/v1/document_sequences/{$sequence->id}", [
            'next_number' => 12345,
            'padding' => 4,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['next_number']);
    });
});

describe('UpdateDocumentSequence backwards-set collision guard', function () {
    it('returns 422 when lowering next_number would re-issue an existing invoice number', function () {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-15 12:00:00', 'UTC'));

        $store = Store::factory()->create(['timezone' => 'UTC']);
        $sequence = DocumentSequence::factory()->create([
            'store_id' => $store->id,
            'document_type' => 'invoice_standard',
            'prefix' => 'INV-',
            'include_year' => true,
            'include_month' => false,
            'padding' => 4,
            'next_number' => 100,
        ]);

        Invoice::factory()->create([
            'store_id' => $store->id,
            'type' => 'standard',
            'number' => 'INV-2026-0050',
        ]);

        expect(fn () => (new UpdateDocumentSequence)($sequence, UpdateDocumentSequenceData::from([
            'next_number' => 50,
        ])))->toThrow(ValidationException::class, 'INV-2026-0050');

        CarbonImmutable::setTestNow();
    });

    it('allows lowering next_number when the re-issued range is unused', function () {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-15 12:00:00', 'UTC'));

        $store = Store::factory()->create(['timezone' => 'UTC']);
        $sequence = DocumentSequence::factory()->create([
            'store_id' => $store->id,
            'document_type' => 'invoice_standard',
            'prefix' => 'INV-',
            'include_year' => true,
            'padding' => 4,
            'next_number' => 100,
        ]);

        $result = (new UpdateDocumentSequence)($sequence, UpdateDocumentSequenceData::from([
            'next_number' => 95,
        ]));

        expect($result->next_number)->toBe(95);

        CarbonImmutable::setTestNow();
    });

    it('does not false-positive against cross-type invoices when lowering a deposit sequence', function () {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-15 12:00:00', 'UTC'));

        $store = Store::factory()->create(['timezone' => 'UTC']);
        $sequence = DocumentSequence::factory()->create([
            'store_id' => $store->id,
            'document_type' => 'invoice_deposit',
            'prefix' => 'INV-',
            'include_year' => true,
            'include_month' => false,
            'padding' => 4,
            'next_number' => 100,
        ]);

        Invoice::factory()->create([
            'store_id' => $store->id,
            'type' => 'standard',
            'number' => 'INV-2026-0050',
        ]);

        $result = (new UpdateDocumentSequence)($sequence, UpdateDocumentSequenceData::from([
            'next_number' => 50,
        ]));

        expect($result->next_number)->toBe(50);

        CarbonImmutable::setTestNow();
    });

    it('always allows raising next_number', function () {
        $store = Store::factory()->create();
        $sequence = DocumentSequence::factory()->create([
            'store_id' => $store->id,
            'document_type' => 'invoice_standard',
            'next_number' => 10,
        ]);

        $result = (new UpdateDocumentSequence)($sequence, UpdateDocumentSequenceData::from([
            'next_number' => 500,
        ]));

        expect($result->next_number)->toBe(500);
    });

    it('skips collision guard for pure document types without projection tables', function () {
        $store = Store::factory()->create(['timezone' => 'UTC']);
        $sequence = DocumentSequence::factory()->create([
            'store_id' => $store->id,
            'document_type' => 'quote',
            'prefix' => 'QUO-',
            'include_year' => true,
            'padding' => 4,
            'next_number' => 100,
        ]);

        $result = (new UpdateDocumentSequence)($sequence, UpdateDocumentSequenceData::from([
            'next_number' => 1,
        ]));

        expect($result->next_number)->toBe(1);
    });

    it('rejects lowering padding when the current next number no longer fits', function () {
        $store = Store::factory()->create();
        $sequence = DocumentSequence::factory()->create([
            'store_id' => $store->id,
            'document_type' => 'quote',
            'next_number' => 1000,
            'padding' => 4,
        ]);

        expect(fn () => (new UpdateDocumentSequence)($sequence, UpdateDocumentSequenceData::from([
            'padding' => 3,
        ])))->toThrow(ValidationException::class);
    });
});

describe('UpdateDocumentSequence migration-backed credit note store scope', function () {
    it('detects collisions on credit notes via backfilled store_id', function () {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-01 09:00:00', 'UTC'));

        $store = Store::factory()->create(['timezone' => 'UTC']);
        $invoice = Invoice::factory()->create(['store_id' => $store->id]);
        $sequence = DocumentSequence::factory()->create([
            'store_id' => $store->id,
            'document_type' => 'credit_note',
            'prefix' => 'CN-',
            'include_year' => true,
            'padding' => 4,
            'next_number' => 20,
        ]);

        DB::table('credit_notes')->insert([
            'id' => app(SequenceAllocator::class)->next('credit_notes'),
            'invoice_id' => $invoice->id,
            'store_id' => $store->id,
            'number' => 'CN-2026-0010',
            'status' => 'issued',
            'subtotal' => -100,
            'tax_total' => -20,
            'total' => -120,
            'currency_code' => 'GBP',
            'exchange_rate' => '1',
            'prices_include_tax' => false,
            'issued_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(fn () => (new UpdateDocumentSequence)($sequence, UpdateDocumentSequenceData::from([
            'next_number' => 10,
        ])))->toThrow(ValidationException::class);

        CarbonImmutable::setTestNow();
    });
});
